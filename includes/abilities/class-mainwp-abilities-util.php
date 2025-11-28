<?php
/**
 * MainWP Abilities Utilities
 *
 * @package MainWP\Dashboard
 */

namespace MainWP\Dashboard;

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

/**
 * Class MainWP_Abilities_Util
 *
 * Shared utility functions for MainWP abilities.
 */
class MainWP_Abilities_Util {

    /**
     * Check if current request has REST API permission.
     *
     * Prioritizes REST API key authentication; falls back to session-based
     * authentication for legacy/admin use. This ensures compatibility with
     * MainWP's consumer key/secret authentication which doesn't require a session.
     *
     * Note: Invalid or missing API key errors (e.g., 'mainwp_rest_authentication_error')
     * are handled earlier in the REST authentication stack by MainWP_REST_Authentication
     * via the 'rest_authentication_errors' filter. By the time this permission callback
     * runs, invalid API requests have already been rejected. We only need to check if
     * a valid REST user exists and verify their capabilities.
     *
     * IMPORTANT: Returns true on success or WP_Error on failure.
     * This is required for abilities to surface proper error codes to consumers.
     *
     * @param mixed $input The ability input (unused but required by signature).
     * @return true|\WP_Error True on success, WP_Error with code on failure.
     */
    public static function check_rest_api_permission( $input = null ) {
        // Prioritize MainWP REST API key authentication.
        // Note: Key-specific errors (invalid key, invalid secret, bad signature) are
        // handled by MainWP_REST_Authentication::check_authentication_error() which
        // hooks into 'rest_authentication_errors' and blocks requests before this runs.
        if ( class_exists( '\MainWP_REST_Authentication' ) ) {
            $auth      = \MainWP_REST_Authentication::get_instance();
            $rest_user = $auth->get_rest_valid_user();

            // If REST auth has validated a user, trust that authentication.
            if ( ! empty( $rest_user ) ) {
                // REST API key is valid, check capability of the associated user.
                if ( ! current_user_can( 'manage_options' ) ) {
                    return new \WP_Error(
                        'mainwp_permission_denied',
                        __( 'API key user does not have sufficient permissions.', 'mainwp' ),
                        [ 'status' => 403 ]
                    );
                }
                return true;
            }
        }

        // Fallback: session-based authentication for admin/legacy use.
        if ( ! is_user_logged_in() ) {
            return new \WP_Error(
                'mainwp_permission_denied',
                __( 'You must be logged in to access this ability.', 'mainwp' ),
                [ 'status' => 401 ]
            );
        }

        // Check for manage_options capability.
        if ( ! current_user_can( 'manage_options' ) ) {
            return new \WP_Error(
                'mainwp_permission_denied',
                __( 'You do not have permission to perform this action.', 'mainwp' ),
                [ 'status' => 403 ]
            );
        }

        return true;
    }

    /**
     * Check if current user can manage sites (write operations).
     *
     * Currently a wrapper over check_rest_api_permission() which already verifies
     * manage_options capability. This method exists as an extension point for future
     * granular permissions (e.g., a dedicated 'mainwp_manage_sites' capability).
     *
     * @param mixed $input The ability input (unused but required by signature).
     * @return true|\WP_Error True on success, WP_Error on failure.
     */
    public static function check_manage_sites_permission( $input = null ) {
        $base = self::check_rest_api_permission( $input );
        if ( is_wp_error( $base ) ) {
            return $base;
        }

        // Future: Add granular 'mainwp_manage_sites' capability check here.
        // For now, manage_options is already verified in check_rest_api_permission().

        return true;
    }

    /**
     * Check if current user can access a specific site.
     *
     * This enforces per-site ACLs beyond the basic REST API permission check.
     * Use this for all site-specific operations (get, sync, update, plugins, themes).
     *
     * IMPORTANT: Returns true on success or WP_Error on failure.
     * For execute callbacks that need a boolean check, use the helper method below.
     *
     * @param int|object $site Site ID or site object.
     * @param mixed      $input The ability input (unused but required by signature).
     * @return true|\WP_Error True on success, WP_Error on failure.
     */
    public static function check_site_access( $site, $input = null ) {
        // First, verify basic REST API permission.
        $base_check = self::check_rest_api_permission( $input );
        if ( is_wp_error( $base_check ) ) {
            return $base_check;
        }

        $site_id = is_object( $site ) ? (int) $site->id : (int) $site;

        // Use MainWP's existing per-site access control.
        if ( class_exists( 'MainWP_System' ) ) {
            $system = MainWP_System::instance();
            if ( method_exists( $system, 'check_site_access' ) ) {
                if ( ! $system->check_site_access( $site_id ) ) {
                    return new \WP_Error(
                        'mainwp_access_denied',
                        __( 'You do not have permission to access this site.', 'mainwp' ),
                        [ 'status' => 403 ]
                    );
                }
            }
        }

        return true;
    }

    /**
     * Check site access and return boolean.
     *
     * Convenience method for execute callbacks that need bool-style checks.
     * Use check_site_access() for permission_callback (returns WP_Error for proper error responses).
     *
     * @param int|object $site Site ID or site object.
     * @param mixed      $input The ability input.
     * @return bool True if access allowed, false otherwise.
     */
    public static function can_access_site( $site, $input = null ): bool {
        return true === self::check_site_access( $site, $input );
    }

    /**
     * Check if site's child plugin meets minimum version requirement.
     *
     * Use this in execute callbacks before performing operations that require
     * child plugin communication. Returns WP_Error with 'mainwp_child_outdated'
     * code if the child version is too low.
     *
     * @param object $site        Site object with version property.
     * @param string $min_version Minimum required version (default: '4.0.0').
     * @return true|\WP_Error True if version OK, WP_Error if outdated.
     */
    public static function check_child_version( $site, string $min_version = '4.0.0' ) {
        // Validate site parameter.
        if ( ! is_object( $site ) || ! isset( $site->id ) ) {
            return new \WP_Error(
                'mainwp_internal_error',
                __( 'Invalid site object provided.', 'mainwp' ),
                [ 'status' => 500 ]
            );
        }

        $child_version = $site->version ?? '0.0.0';
        if ( version_compare( $child_version, $min_version, '<' ) ) {
            return new \WP_Error(
                'mainwp_child_outdated',
                sprintf(
                    /* translators: %s: minimum version */
                    __( 'Child plugin version %s or higher required.', 'mainwp' ),
                    $min_version
                ),
                [ 'status' => 400, 'site_id' => (int) $site->id ]
            );
        }
        return true;
    }

    /**
     * Check site access for batch operations.
     *
     * Filters a list of site identifiers to only those the user can access.
     * Returns both accessible sites and access-denied errors.
     *
     * @param array $site_ids_or_domains Array of site IDs or URLs/domains.
     * @param mixed $input               The ability input (unused but required by signature).
     * @return array Array with 'allowed' (accessible sites) and 'denied' (access errors).
     */
    public static function check_batch_site_access( array $site_ids_or_domains, $input = null ): array {
        $allowed = [];
        $denied  = [];

        foreach ( $site_ids_or_domains as $identifier ) {
            $site = self::resolve_site( $identifier );

            if ( is_wp_error( $site ) ) {
                $error_data = $site->get_error_data();
                $denied[]   = [
                    'identifier' => $identifier,
                    'code'       => $site->get_error_code(),
                    'message'    => $site->get_error_message(),
                    'status'     => isset( $error_data['status'] ) ? $error_data['status'] : null,
                ];
                continue;
            }

            $access_check = self::check_site_access( $site, $input );
            if ( is_wp_error( $access_check ) ) {
                $error_data = $access_check->get_error_data();
                $denied[]   = [
                    'identifier' => $identifier,
                    'code'       => $access_check->get_error_code(),
                    'message'    => $access_check->get_error_message(),
                    'status'     => isset( $error_data['status'] ) ? $error_data['status'] : null,
                ];
                continue;
            }

            $allowed[] = $site;
        }

        return [
            'allowed' => $allowed,
            'denied'  => $denied,
        ];
    }

    /**
     * Resolve a site identifier to a MainWP site object.
     *
     * Resolution order:
     * 1. If numeric → treat as MainWP site ID
     * 2. Otherwise → treat as URL/domain and resolve
     *
     * @param int|string $site_id_or_domain Site ID or URL/domain.
     * @return object|\WP_Error Site object on success, WP_Error on failure.
     */
    public static function resolve_site( $site_id_or_domain ) {
        if ( ! class_exists( 'MainWP_DB' ) ) {
            return new \WP_Error(
                'mainwp_internal_error',
                __( 'MainWP database class is not available.', 'mainwp' ),
                [ 'status' => 500 ]
            );
        }

        $db = MainWP_DB::instance();

        // Numeric → try as site ID.
        if ( is_numeric( $site_id_or_domain ) ) {
            $site = $db->get_website_by_id( (int) $site_id_or_domain );
            if ( $site ) {
                return $site;
            }
            return new \WP_Error(
                'mainwp_site_not_found',
                sprintf(
                    /* translators: %d: site ID */
                    __( 'No site found with ID %d.', 'mainwp' ),
                    (int) $site_id_or_domain
                ),
                [ 'status' => 404 ]
            );
        }

        // Non-numeric → treat as URL/domain.
        $url  = self::normalize_url( $site_id_or_domain );
        $site = $db->get_websites_by_url( $url );

        if ( $site && ! empty( $site ) ) {
            return is_array( $site ) ? $site[0] : $site;
        }

        return new \WP_Error(
            'mainwp_site_not_found',
            sprintf(
                /* translators: %s: domain or URL */
                __( 'No site found matching "%s".', 'mainwp' ),
                $site_id_or_domain
            ),
            [ 'status' => 404 ]
        );
    }

    /**
     * Resolve multiple site identifiers.
     *
     * @param array $site_ids_or_domains Array of site IDs or URLs/domains.
     * @return array Array with 'sites' (resolved) and 'errors' (failed).
     */
    public static function resolve_sites( array $site_ids_or_domains ): array {
        $sites  = [];
        $errors = [];

        foreach ( $site_ids_or_domains as $identifier ) {
            $site = self::resolve_site( $identifier );
            if ( is_wp_error( $site ) ) {
                $error_data = $site->get_error_data();
                $errors[]   = [
                    'identifier' => $identifier,
                    'code'       => $site->get_error_code(),
                    'message'    => $site->get_error_message(),
                    'status'     => isset( $error_data['status'] ) ? $error_data['status'] : null,
                ];
            } else {
                $sites[] = $site;
            }
        }

        return [
            'sites'  => $sites,
            'errors' => $errors,
        ];
    }

    /**
     * Normalize a URL for site lookup.
     *
     * IMPORTANT: This normalization is for site resolution only.
     *
     * Handles:
     * - Protocol stripping (https://, http://)
     * - www prefix removal
     * - Trailing slash enforcement
     *
     * LIMITATIONS (prefer site ID for these cases):
     * - Port numbers (example.com:8080) - not stripped, may fail to match
     * - Subdirectory multisites (example.com/site1 vs example.com/site2) - may collide
     * - URL-encoded characters - not decoded
     * - IDN/punycode domains - not normalized
     *
     * RECOMMENDATION: For ambiguous cases or programmatic access, always use
     * site ID instead of URL/domain.
     *
     * @param string $url URL to normalize.
     * @return string Normalized URL.
     */
    public static function normalize_url( string $url ): string {
        // Remove protocol and www prefix.
        $url = preg_replace( '#^https?://(www\.)?#i', '', $url );

        // Ensure trailing slash.
        $url = trailingslashit( $url );

        return $url;
    }

    /**
     * Create a permission callback for site-specific abilities.
     *
     * The Abilities API passes $input to the permission_callback, but our
     * check_site_access() method expects a resolved site object. This wrapper
     * resolves the site from input first, then performs the access check.
     *
     * IMPORTANT: Use this for any ability that operates on a single site
     * identified by 'site_id_or_domain' in the input schema.
     *
     * @param string $input_key The input key containing the site identifier (default: 'site_id_or_domain').
     * @return callable Permission callback closure.
     */
    public static function make_site_permission_callback( string $input_key = 'site_id_or_domain' ): callable {
        return function ( $input ) use ( $input_key ) {
            // First check basic REST API permission.
            $base_check = self::check_rest_api_permission( $input );
            if ( is_wp_error( $base_check ) ) {
                return $base_check;
            }

            // Resolve the site from input.
            $identifier = $input[ $input_key ] ?? null;
            if ( null === $identifier ) {
                return new \WP_Error(
                    'mainwp_invalid_input',
                    __( 'Site identifier is required.', 'mainwp' ),
                    [ 'status' => 400 ]
                );
            }

            $site = self::resolve_site( $identifier );
            if ( is_wp_error( $site ) ) {
                return $site;
            }

            // Check per-site access.
            return self::check_site_access( $site, $input );
        };
    }

    /**
     * Map a site object to the standard output format.
     *
     * @param object $site          Site object from database.
     * @param bool   $full_details  Whether to include full site details (default: false).
     * @param bool   $include_stats Whether to include site statistics (default: false).
     * @return array Formatted site data.
     */
    public static function format_site_for_output( $site, bool $full_details = false, bool $include_stats = false ): array {
        $output = array(
            'id'        => (int) $site->id,
            'url'       => (string) $site->url,
            'name'      => (string) $site->name,
            'status'    => self::get_site_status( $site ),
            'client_id' => isset( $site->client_id ) && $site->client_id > 0
                ? (int) $site->client_id
                : null,
        );

        if ( $full_details ) {
            // Add extended site details for single-site retrieval.
            $output['admin_username'] = $site->adminname ?? '';

            // Get site_info from DB option (stored as JSON).
            // Pass full $site object to allow property check before DB query.
            $site_info = MainWP_DB::instance()->get_website_option( $site, 'site_info' );
            $site_info = ! empty( $site_info ) ? json_decode( $site_info, true ) : array();

            $output['wp_version']    = isset( $site_info['wpversion'] ) ? $site_info['wpversion'] : '';
            $output['php_version']   = isset( $site_info['phpversion'] ) ? $site_info['phpversion'] : '';
            $output['child_version'] = $site->version ?? '';

            // Format last_sync as ISO 8601 timestamp.
            $output['last_sync'] = ! empty( $site->dtsSync ) ? gmdate( 'c', (int) $site->dtsSync ) : null;

            $output['notes'] = $site->note ?? '';
        }

        // Include site statistics if requested.
        if ( $include_stats ) {
            $output['stats'] = self::get_site_stats( $site );
        }

        return $output;
    }

    /**
     * Get site statistics for include_stats option.
     *
     * @param object $site Site object from database.
     * @return array Site statistics array.
     */
    public static function get_site_stats( $site ): array {
        // Count plugin updates.
        $plugin_updates = ! empty( $site->plugin_upgrades ) ? json_decode( $site->plugin_upgrades, true ) : array();
        $plugin_count   = is_array( $plugin_updates ) ? count( $plugin_updates ) : 0;

        // Count theme updates.
        $theme_updates = ! empty( $site->theme_upgrades ) ? json_decode( $site->theme_upgrades, true ) : array();
        $theme_count   = is_array( $theme_updates ) ? count( $theme_updates ) : 0;

        // Check for WordPress core update.
        // Pass full $site object to allow property check before DB query.
        $wp_upgrades         = MainWP_DB::instance()->get_website_option( $site, 'wp_upgrades' );
        $wp_upgrades         = ! empty( $wp_upgrades ) ? json_decode( $wp_upgrades, true ) : array();
        $wp_update_available = is_array( $wp_upgrades ) && ! empty( $wp_upgrades );

        // Get health score if available.
        $health_score = null;
        if ( isset( $site->health_value ) ) {
            $health_score = (int) $site->health_value;
        }

        return array(
            'plugin_updates'      => $plugin_count,
            'theme_updates'       => $theme_count,
            'wp_update_available' => $wp_update_available,
            'health_score'        => $health_score,
        );
    }

    /**
     * Get site connection status string.
     *
     * @param object $site Site object.
     * @return string Status string: 'connected', 'disconnected', or 'suspended'.
     */
    public static function get_site_status( $site ): string {
        // Check if site is suspended.
        if ( isset( $site->suspended ) && 1 === (int) $site->suspended ) {
            return 'suspended';
        }

        // Check offline status.
        if ( isset( $site->offline_check_result ) && -1 === (int) $site->offline_check_result ) {
            return 'disconnected';
        }

        // Check for sync errors.
        if ( isset( $site->sync_errors ) && ! empty( $site->sync_errors ) ) {
            return 'disconnected';
        }

        return 'connected';
    }

    /**
     * Queue a batch sync operation for background processing.
     *
     * Used when >50 sites need to be synced to avoid request timeouts.
     * Stores job data in a transient and schedules a cron event for processing.
     *
     * @param array $sites Array of site objects to sync.
     * @return string|\WP_Error Job ID for status polling, or WP_Error on failure.
     */
    public static function queue_batch_sync( array $sites ) {
        // Generate unique job ID.
        $job_id = 'sync_' . wp_generate_uuid4();

        // Extract site IDs from site objects.
        $site_ids = array_map(
            function ( $site ) {
                return (int) $site->id;
            },
            $sites
        );

        // Store job data in transient (expires in 24 hours).
        // Structure aligns with REST v2 job status endpoint expectations.
        // Status values: 'queued' -> 'processing' -> 'completed' | 'failed'.
        $job_data = array(
            'job_type'   => 'sync',                  // Distinguishes from 'update' jobs.
            'sites'      => $site_ids,
            'status'     => 'queued',                // queued | processing | completed | failed.
            'created'    => time(),
            'started'    => null,
            'completed'  => null,
            'synced'     => array(),                 // Successfully synced sites.
            'errors'     => array(),                 // Failed syncs array.
            'progress'   => 0,                       // 0-100 percentage.
            'total'      => count( $site_ids ),      // Total sites to process.
            'processed'  => 0,                       // Sites processed so far.
        );

        set_transient( 'mainwp_sync_job_' . $job_id, $job_data, DAY_IN_SECONDS );

        // Verify transient was stored successfully.
        $stored = get_transient( 'mainwp_sync_job_' . $job_id );
        if ( empty( $stored ) || ! is_array( $stored ) ) {
            return new \WP_Error(
                'mainwp_queue_failed',
                __( 'Failed to store sync job data. Please try again.', 'mainwp' ),
                array( 'status' => 500 )
            );
        }

        // Only schedule if no matching event already exists.
        if ( ! wp_next_scheduled( 'mainwp_process_sync_job', array( $job_id ) ) ) {
            wp_schedule_single_event( time() + 60, 'mainwp_process_sync_job', array( $job_id ) );
        }

        return $job_id;
    }

    /**
     * Get batch sync job status.
     *
     * @param string $job_id Job ID to check.
     * @return array|null Job data array or null if not found.
     */
    public static function get_batch_sync_status( string $job_id ): ?array {
        $job_data = get_transient( 'mainwp_sync_job_' . $job_id );
        return is_array( $job_data ) ? $job_data : null;
    }

    /**
     * Queue a batch update operation for background processing.
     *
     * Used when >50 sites need updates to avoid request timeouts.
     * Stores job data in a transient and schedules a cron event for processing.
     *
     * @param array $sites         Array of site objects to update.
     * @param array $update_params Update parameters with keys: types (array), specific_items (array).
     * @return string|\WP_Error Job ID for status polling, or WP_Error on failure.
     */
    public static function queue_batch_updates( array $sites, array $update_params ) {
        // Generate unique job ID.
        $job_id = 'update_' . wp_generate_uuid4();

        // Extract site IDs from site objects.
        $site_ids = array_map(
            function ( $site ) {
                return (int) $site->id;
            },
            $sites
        );

        // Normalize and validate types parameter.
        $allowed_types = array( 'core', 'plugins', 'themes', 'translations' );
        $types         = isset( $update_params['types'] ) && is_array( $update_params['types'] )
            ? array_values( array_intersect( $update_params['types'], $allowed_types ) )
            : array();

        // Normalize specific_items to array of strings.
        $specific_items = array();
        if ( isset( $update_params['specific_items'] ) && is_array( $update_params['specific_items'] ) ) {
            foreach ( $update_params['specific_items'] as $item ) {
                if ( is_string( $item ) && '' !== $item ) {
                    $specific_items[] = $item;
                }
            }
        }

        // Store job data in transient (expires in 24 hours).
        // Structure aligns with REST v2 job status endpoint expectations.
        // Status values: 'queued' -> 'processing' -> 'completed' | 'failed'.
        $job_data = array(
            'job_type'       => 'update',            // Distinguishes from 'sync' jobs.
            'sites'          => $site_ids,
            'types'          => $types,
            'specific_items' => $specific_items,
            'status'         => 'queued',            // queued | processing | completed | failed.
            'created'        => time(),
            'started'        => null,
            'completed'      => null,
            'updated'        => array(),             // Successful updates array.
            'errors'         => array(),             // Failed updates array.
            'progress'       => 0,                   // 0-100 percentage.
            'total'          => count( $site_ids ),  // Total sites to process.
            'processed'      => 0,                   // Sites processed so far.
        );

        set_transient( 'mainwp_update_job_' . $job_id, $job_data, DAY_IN_SECONDS );

        // Verify transient was stored successfully.
        $stored = get_transient( 'mainwp_update_job_' . $job_id );
        if ( empty( $stored ) || ! is_array( $stored ) ) {
            return new \WP_Error(
                'mainwp_queue_failed',
                __( 'Failed to store update job data. Please try again.', 'mainwp' ),
                array( 'status' => 500 )
            );
        }

        // Only schedule if no matching event already exists.
        if ( ! wp_next_scheduled( 'mainwp_process_update_job', array( $job_id ) ) ) {
            wp_schedule_single_event( time() + 60, 'mainwp_process_update_job', array( $job_id ) );
        }

        return $job_id;
    }

    /**
     * Get batch update job status.
     *
     * @param string $job_id Job ID to check.
     * @return array|null Job data array or null if not found.
     */
    public static function get_batch_update_status( string $job_id ): ?array {
        $job_data = get_transient( 'mainwp_update_job_' . $job_id );
        return is_array( $job_data ) ? $job_data : null;
    }
}
