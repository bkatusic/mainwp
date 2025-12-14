<?php
/**
 * MainWP Abilities Cron Handlers
 *
 * Processes batch operations (sync, update, batch) queued via the Abilities API.
 * Jobs >200 sites are queued to transients and processed in chunks by these handlers.
 *
 * @package MainWP\Dashboard
 */

namespace MainWP\Dashboard;

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

/**
 * Class MainWP_Abilities_Cron
 *
 * Handles cron processing for batch Abilities API operations.
 * Processes jobs in chunks of 20 sites with timeout protection and reschedule logic.
 */
class MainWP_Abilities_Cron {

    /**
     * Singleton instance.
     *
     * @var null|self
     */
    private static $instance = null;

    /**
     * Get singleton instance.
     *
     * @return self
     */
    public static function instance(): self {
        if ( null === static::$instance ) {
            static::$instance = new self();
        }
        return static::$instance;
    }

    /**
     * Constructor.
     *
     * Registers cron action handlers for batch processing.
     */
    public function __construct() {
        // Register cron handlers for batch job processing.
        add_action( 'mainwp_process_sync_job', array( $this, 'process_sync_job' ) );
        add_action( 'mainwp_process_update_job', array( $this, 'process_update_job' ) );
        add_action( 'mainwp_process_batch_job', array( $this, 'process_batch_job' ) );
    }

    /**
     * Check if debug logging is enabled for cron handlers.
     *
     * Use the `mainwp_abilities_cron_debug_logging` filter to disable verbose
     * logging during cron processing. Defaults to true (logging enabled).
     *
     * @return bool True if debug logging is enabled, false otherwise.
     */
    private function is_debug_logging_enabled(): bool {
        return (bool) apply_filters( 'mainwp_abilities_cron_debug_logging', true );
    }

    /**
     * Log a debug message if debug logging is enabled.
     *
     * @param string $message Message to log.
     * @return void
     */
    private function log_debug( string $message ): void {
        if ( $this->is_debug_logging_enabled() ) {
            MainWP_Logger::instance()->log_events( 'debug-abilities', $message );
        }
    }

    /**
     * Process a batch sync job.
     *
     * Called via WP-Cron when a sync job is scheduled.
     * Processes sites in chunks, updates progress, and reschedules until complete.
     *
     * @param string $job_id Job ID to process.
     * @return void
     */
    public function process_sync_job( $job_id ): void {
        // Environment setup for long-running process.
        ignore_user_abort( true );
        MainWP_System_Utility::set_time_limit( 0 );

        // Load job from transient.
        $job = get_transient( 'mainwp_sync_job_' . $job_id );
        if ( empty( $job ) || ! is_array( $job ) ) {
            $this->log_debug( 'Sync job not found or expired: ' . $job_id );
            return;
        }

        // Timeout protection (4 hours max).
        if ( ! empty( $job['started'] ) && time() > $job['started'] + 4 * HOUR_IN_SECONDS ) {
            $job['status']       = 'failed';
            $job['completed']    = time();
            $job['job_timed_out'] = true;
            $job['errors'][]     = array(
                'site_id' => 0,
                'code'    => 'timeout',
                'message' => __( 'Job timed out after 4 hours.', 'mainwp' ),
            );
            set_transient( 'mainwp_sync_job_' . $job_id, $job, DAY_IN_SECONDS );
            $this->log_debug( 'Sync job timed out: ' . $job_id );
            return;
        }

        // Status transition: queued -> processing.
        if ( 'queued' === $job['status'] ) {
            $job['status']  = 'processing';
            $job['started'] = time();
        }

        // Chunk processing (default 20 sites per run).
        $chunk_size = apply_filters( 'mainwp_abilities_cron_chunk_size', 20 );

        // Calculate already processed site IDs.
        $processed_ids = array_merge(
            $job['synced'],
            array_column( $job['errors'], 'site_id' )
        );

        // Get remaining sites to process.
        $remaining = array_values( array_diff( $job['sites'], $processed_ids ) );
        $chunk     = array_slice( $remaining, 0, $chunk_size );

        $this->log_debug(
            sprintf(
                'Processing sync job %s: %d sites in chunk, %d remaining',
                $job_id,
                count( $chunk ),
                count( $remaining )
            )
        );

        // Process each site in the chunk.
        foreach ( $chunk as $site_id ) {
            try {
                $website = MainWP_DB::instance()->get_website_by_id( $site_id );

                if ( empty( $website ) ) {
                    $job['errors'][] = array(
                        'site_id' => $site_id,
                        'code'    => 'site_not_found',
                        'message' => __( 'Site not found.', 'mainwp' ),
                    );
                    ++$job['processed'];
                    continue;
                }

                // Call sync_site: false for force fetch, true for allow disconnect, false for clear session.
                $result = MainWP_Sync::sync_site( $website, false, true, false );

                if ( true === $result ) {
                    $job['synced'][] = $site_id;
                } else {
                    $job['errors'][] = array(
                        'site_id' => $site_id,
                        'code'    => 'sync_failed',
                        'message' => __( 'Sync returned false.', 'mainwp' ),
                    );
                }
            } catch ( \Exception $e ) {
                $job['errors'][] = array(
                    'site_id' => $site_id,
                    'code'    => 'exception',
                    'message' => $e->getMessage(),
                );
            }

            ++$job['processed'];
            $job['progress'] = $job['total'] > 0
                ? round( ( $job['processed'] / $job['total'] ) * 100 )
                : 0;
        }

        // Save progress.
        set_transient( 'mainwp_sync_job_' . $job_id, $job, DAY_IN_SECONDS );

        // Recalculate remaining after processing.
        $processed_ids = array_merge(
            $job['synced'],
            array_column( $job['errors'], 'site_id' )
        );
        $remaining     = array_values( array_diff( $job['sites'], $processed_ids ) );

        // Reschedule or complete.
        if ( count( $remaining ) > 0 ) {
            // Schedule next chunk (30 second delay).
            if ( ! wp_next_scheduled( 'mainwp_process_sync_job', array( $job_id ) ) ) {
                wp_schedule_single_event( time() + 30, 'mainwp_process_sync_job', array( $job_id ) );
            }
            $this->log_debug(
                sprintf( 'Sync job %s rescheduled: %d sites remaining', $job_id, count( $remaining ) )
            );
        } else {
            // Job complete.
            $job['status']    = 'completed';
            $job['completed'] = time();
            $job['progress']  = 100;
            set_transient( 'mainwp_sync_job_' . $job_id, $job, DAY_IN_SECONDS );
            $this->log_debug(
                sprintf(
                    'Sync job %s completed: %d synced, %d errors',
                    $job_id,
                    count( $job['synced'] ),
                    count( $job['errors'] )
                )
            );
        }
    }

    /**
     * Process a batch update job.
     *
     * Called via WP-Cron when an update job is scheduled.
     * Processes sites in chunks, applying updates by type, and reschedules until complete.
     *
     * @param string $job_id Job ID to process.
     * @return void
     */
    public function process_update_job( $job_id ): void {
        // Environment setup for long-running process.
        ignore_user_abort( true );
        MainWP_System_Utility::set_time_limit( 0 );

        // Load job from transient.
        $job = get_transient( 'mainwp_update_job_' . $job_id );
        if ( empty( $job ) || ! is_array( $job ) ) {
            $this->log_debug( 'Update job not found or expired: ' . $job_id );
            return;
        }

        // Timeout protection (4 hours max).
        if ( ! empty( $job['started'] ) && time() > $job['started'] + 4 * HOUR_IN_SECONDS ) {
            $job['status']       = 'failed';
            $job['completed']    = time();
            $job['job_timed_out'] = true;
            $job['errors'][]     = array(
                'site_id' => 0,
                'code'    => 'timeout',
                'message' => __( 'Job timed out after 4 hours.', 'mainwp' ),
            );
            set_transient( 'mainwp_update_job_' . $job_id, $job, DAY_IN_SECONDS );
            $this->log_debug( 'Update job timed out: ' . $job_id );
            return;
        }

        // Status transition: queued -> processing.
        if ( 'queued' === $job['status'] ) {
            $job['status']  = 'processing';
            $job['started'] = time();
        }

        // Chunk processing (default 20 sites per run).
        $chunk_size = apply_filters( 'mainwp_abilities_cron_chunk_size', 20 );

        // Calculate already processed site IDs.
        $processed_ids = array_merge(
            $job['updated'],
            array_column( $job['errors'], 'site_id' )
        );

        // Get remaining sites to process.
        $remaining = array_values( array_diff( $job['sites'], $processed_ids ) );
        $chunk     = array_slice( $remaining, 0, $chunk_size );

        $this->log_debug(
            sprintf(
                'Processing update job %s: %d sites in chunk, %d remaining, types: %s',
                $job_id,
                count( $chunk ),
                count( $remaining ),
                implode( ',', $job['types'] )
            )
        );

        // Process each site in the chunk.
        foreach ( $chunk as $site_id ) {
            try {
                $website = MainWP_DB::instance()->get_website_by_id( $site_id );

                if ( empty( $website ) ) {
                    $job['errors'][] = array(
                        'site_id' => $site_id,
                        'code'    => 'site_not_found',
                        'message' => __( 'Site not found.', 'mainwp' ),
                    );
                    ++$job['processed'];
                    continue;
                }

                $site_success = true;
                $site_errors  = array();

                // Process each update type.
                foreach ( $job['types'] as $type ) {
                    try {
                        switch ( $type ) {
                            case 'core':
                                MainWP_Connect::fetch_url_authed( $website, 'upgrade' );
                                break;

                            case 'plugins':
                                $slugs = $this->get_update_slugs_for_site( $website, 'plugin', $job['specific_items'] );
                                if ( ! empty( $slugs ) ) {
                                    $slugs_list = implode( ',', $slugs );

                                    /**
                                     * Fires before plugin/theme/translation update actions.
                                     *
                                     * @since 4.1
                                     *
                                     * @param string $type    Update type: 'plugin', 'theme', or 'translation'.
                                     * @param string $list    Comma-separated list of slugs being updated.
                                     * @param object $website Site object.
                                     */
                                    do_action( 'mainwp_before_plugin_theme_translation_update', 'plugin', $slugs_list, $website );

                                    $information = MainWP_Connect::fetch_url_authed(
                                        $website,
                                        'upgradeplugintheme',
                                        array(
                                            'type' => 'plugin',
                                            'list' => urldecode( $slugs_list ),
                                        )
                                    );

                                    /**
                                     * Fires after plugin/theme/translation update actions.
                                     *
                                     * @since 4.1
                                     *
                                     * @param array  $information Response from child site.
                                     * @param string $type        Update type: 'plugin', 'theme', or 'translation'.
                                     * @param string $list        Comma-separated list of slugs that were updated.
                                     * @param object $website     Site object.
                                     */
                                    do_action( 'mainwp_after_plugin_theme_translation_update', $information, 'plugin', $slugs_list, $website );

                                    // Sync site data immediately if child returned sync info.
                                    if ( isset( $information['sync'] ) && ! empty( $information['sync'] ) ) {
                                        MainWP_Sync::sync_information_array( $website, $information['sync'] );
                                    }
                                }
                                break;

                            case 'themes':
                                $slugs = $this->get_update_slugs_for_site( $website, 'theme', $job['specific_items'] );
                                if ( ! empty( $slugs ) ) {
                                    $slugs_list = implode( ',', $slugs );

                                    /** This action is documented in includes/abilities/class-mainwp-abilities-cron.php */
                                    do_action( 'mainwp_before_plugin_theme_translation_update', 'theme', $slugs_list, $website );

                                    $information = MainWP_Connect::fetch_url_authed(
                                        $website,
                                        'upgradeplugintheme',
                                        array(
                                            'type' => 'theme',
                                            'list' => urldecode( $slugs_list ),
                                        )
                                    );

                                    /** This action is documented in includes/abilities/class-mainwp-abilities-cron.php */
                                    do_action( 'mainwp_after_plugin_theme_translation_update', $information, 'theme', $slugs_list, $website );

                                    // Sync site data immediately if child returned sync info.
                                    if ( isset( $information['sync'] ) && ! empty( $information['sync'] ) ) {
                                        MainWP_Sync::sync_information_array( $website, $information['sync'] );
                                    }
                                }
                                break;

                            case 'translations':
                                MainWP_Connect::fetch_url_authed( $website, 'upgradetranslation' );
                                break;
                        }
                    } catch ( \Exception $e ) {
                        $site_success  = false;
                        $site_errors[] = $type . ': ' . $e->getMessage();
                    }
                }

                if ( $site_success && empty( $site_errors ) ) {
                    $job['updated'][] = $site_id;
                } else {
                    $job['errors'][] = array(
                        'site_id' => $site_id,
                        'code'    => 'update_failed',
                        'message' => implode( '; ', $site_errors ),
                    );
                }
            } catch ( \Exception $e ) {
                $job['errors'][] = array(
                    'site_id' => $site_id,
                    'code'    => 'exception',
                    'message' => $e->getMessage(),
                );
            }

            ++$job['processed'];
            $job['progress'] = $job['total'] > 0
                ? round( ( $job['processed'] / $job['total'] ) * 100 )
                : 0;
        }

        // Save progress.
        set_transient( 'mainwp_update_job_' . $job_id, $job, DAY_IN_SECONDS );

        // Recalculate remaining after processing.
        $processed_ids = array_merge(
            $job['updated'],
            array_column( $job['errors'], 'site_id' )
        );
        $remaining     = array_values( array_diff( $job['sites'], $processed_ids ) );

        // Reschedule or complete.
        if ( count( $remaining ) > 0 ) {
            // Schedule next chunk (30 second delay).
            if ( ! wp_next_scheduled( 'mainwp_process_update_job', array( $job_id ) ) ) {
                wp_schedule_single_event( time() + 30, 'mainwp_process_update_job', array( $job_id ) );
            }
            $this->log_debug(
                sprintf( 'Update job %s rescheduled: %d sites remaining', $job_id, count( $remaining ) )
            );
        } else {
            // Job complete.
            $job['status']    = 'completed';
            $job['completed'] = time();
            $job['progress']  = 100;
            set_transient( 'mainwp_update_job_' . $job_id, $job, DAY_IN_SECONDS );
            $this->log_debug(
                sprintf(
                    'Update job %s completed: %d updated, %d errors',
                    $job_id,
                    count( $job['updated'] ),
                    count( $job['errors'] )
                )
            );
        }
    }

    /**
     * Get update slugs for a site, optionally filtered by specific items.
     *
     * @param object $website        Site object.
     * @param string $type           Type: 'plugin' or 'theme'.
     * @param array  $specific_items Optional array of specific slugs to filter.
     * @return array Array of slugs to update.
     */
    private function get_update_slugs_for_site( $website, string $type, array $specific_items = array() ): array {
        $slugs = array();

        if ( 'plugin' === $type ) {
            $updates = json_decode( $website->plugin_upgrades, true );
        } else {
            $updates = json_decode( $website->theme_upgrades, true );
        }

        if ( ! is_array( $updates ) ) {
            $updates = array();
        }

        foreach ( $updates as $slug => $info ) {
            // If specific items provided, filter to only those.
            if ( ! empty( $specific_items ) && ! in_array( $slug, $specific_items, true ) ) {
                continue;
            }
            $slugs[] = $slug;
        }

        return $slugs;
    }

    /**
     * Process a batch operation job (reconnect, disconnect, check, suspend).
     *
     * Called via WP-Cron when a batch operation is scheduled.
     * Processes sites in chunks and reschedules until complete.
     *
     * @param string $job_id Job ID to process.
     * @return void
     */
    public function process_batch_job( $job_id ): void {
        // Environment setup for long-running process.
        ignore_user_abort( true );
        MainWP_System_Utility::set_time_limit( 0 );

        // Load job from transient.
        $job = get_transient( 'mainwp_batch_job_' . $job_id );
        if ( empty( $job ) || ! is_array( $job ) ) {
            $this->log_debug( 'Batch job not found or expired: ' . $job_id );
            return;
        }

        // Timeout protection (4 hours max).
        if ( ! empty( $job['started'] ) && time() > $job['started'] + 4 * HOUR_IN_SECONDS ) {
            $job['status']       = 'failed';
            $job['completed']    = time();
            $job['job_timed_out'] = true;
            $job['errors'][]     = array(
                'site_id' => 0,
                'code'    => 'timeout',
                'message' => __( 'Job timed out after 4 hours.', 'mainwp' ),
            );
            set_transient( 'mainwp_batch_job_' . $job_id, $job, DAY_IN_SECONDS );
            $this->log_debug( 'Batch job timed out: ' . $job_id );
            return;
        }

        // Status transition: queued -> processing.
        if ( 'queued' === $job['status'] ) {
            $job['status']  = 'processing';
            $job['started'] = time();
        }

        // Chunk processing (default 20 sites per run).
        $chunk_size = apply_filters( 'mainwp_abilities_cron_chunk_size', 20 );

        // Calculate already processed site IDs.
        $processed_ids = array_merge(
            $job['successful'],
            array_column( $job['errors'], 'site_id' )
        );

        // Get remaining sites to process.
        $remaining = array_values( array_diff( $job['sites'], $processed_ids ) );
        $chunk     = array_slice( $remaining, 0, $chunk_size );

        $this->log_debug(
            sprintf(
                'Processing batch job %s (%s): %d sites in chunk, %d remaining',
                $job_id,
                $job['job_type'],
                count( $chunk ),
                count( $remaining )
            )
        );

        // Process each site in the chunk.
        foreach ( $chunk as $site_id ) {
            try {
                $website = MainWP_DB::instance()->get_website_by_id( $site_id );

                if ( empty( $website ) ) {
                    $job['errors'][] = array(
                        'site_id' => $site_id,
                        'code'    => 'site_not_found',
                        'message' => __( 'Site not found.', 'mainwp' ),
                    );
                    ++$job['processed'];
                    continue;
                }

                $success = false;
                $error   = '';

                // Execute operation based on job type.
                switch ( $job['job_type'] ) {
                    case 'reconnect':
                        try {
                            $result  = MainWP_Manage_Sites_View::m_reconnect_site( $website );
                            $success = (bool) $result;
                            if ( ! $success ) {
                                $error = __( 'Reconnect returned false.', 'mainwp' );
                            }
                        } catch ( \Exception $e ) {
                            $error = $e->getMessage();
                        }
                        break;

                    case 'disconnect':
                        // Disconnect is a DB update, always succeeds.
                        MainWP_DB::instance()->update_website_sync_values(
                            $website->id,
                            array( 'sync_errors' => __( 'Manually disconnected', 'mainwp' ) )
                        );
                        $success = true;
                        break;

                    case 'check':
                        $result = MainWP_Monitoring_Handler::handle_check_website( $website->id );
                        if ( is_wp_error( $result ) ) {
                            $error = $result->get_error_message();
                        } else {
                            $success = true;
                        }
                        break;

                    case 'suspend':
                        // Suspend is a DB update, always succeeds.
                        MainWP_DB::instance()->update_website_values(
                            $website->id,
                            array( 'suspended' => 1 )
                        );
                        /**
                         * Fires when a site is suspended.
                         *
                         * @param object $website Site object.
                         * @param int    $status  Suspension status (1 = suspended).
                         */
                        do_action( 'mainwp_site_suspended', $website, 1 );
                        $success = true;
                        break;

                    default:
                        $error = __( 'Unknown operation type.', 'mainwp' );
                }

                if ( $success ) {
                    $job['successful'][] = $site_id;
                } else {
                    $job['errors'][] = array(
                        'site_id' => $site_id,
                        'code'    => $job['job_type'] . '_failed',
                        'message' => $error,
                    );
                }
            } catch ( \Exception $e ) {
                $job['errors'][] = array(
                    'site_id' => $site_id,
                    'code'    => 'exception',
                    'message' => $e->getMessage(),
                );
            }

            ++$job['processed'];
            $job['progress'] = $job['total'] > 0
                ? round( ( $job['processed'] / $job['total'] ) * 100 )
                : 0;
        }

        // Save progress.
        set_transient( 'mainwp_batch_job_' . $job_id, $job, DAY_IN_SECONDS );

        // Recalculate remaining after processing.
        $processed_ids = array_merge(
            $job['successful'],
            array_column( $job['errors'], 'site_id' )
        );
        $remaining     = array_values( array_diff( $job['sites'], $processed_ids ) );

        // Reschedule or complete.
        if ( count( $remaining ) > 0 ) {
            // Schedule next chunk (30 second delay).
            if ( ! wp_next_scheduled( 'mainwp_process_batch_job', array( $job_id ) ) ) {
                wp_schedule_single_event( time() + 30, 'mainwp_process_batch_job', array( $job_id ) );
            }
            $this->log_debug(
                sprintf( 'Batch job %s rescheduled: %d sites remaining', $job_id, count( $remaining ) )
            );
        } else {
            // Job complete.
            $job['status']    = 'completed';
            $job['completed'] = time();
            $job['progress']  = 100;
            set_transient( 'mainwp_batch_job_' . $job_id, $job, DAY_IN_SECONDS );
            $this->log_debug(
                sprintf(
                    'Batch job %s (%s) completed: %d successful, %d errors',
                    $job_id,
                    $job['job_type'],
                    count( $job['successful'] ),
                    count( $job['errors'] )
                )
            );
        }
    }
}
