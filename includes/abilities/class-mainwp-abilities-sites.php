<?php
/**
 * MainWP Sites Abilities
 *
 * @package MainWP\Dashboard
 */

namespace MainWP\Dashboard;

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

/**
 * Class MainWP_Abilities_Sites
 *
 * Registers and implements site-related abilities for the MainWP Dashboard.
 *
 * This class provides 5 abilities:
 * - mainwp/list-sites-v1: List MainWP child sites with pagination and filtering
 * - mainwp/get-site-v1: Get detailed information about a single site
 * - mainwp/sync-sites-v1: Trigger synchronization for one or more sites
 * - mainwp/get-site-plugins-v1: Get list of plugins installed on a site
 * - mainwp/get-site-themes-v1: Get list of themes installed on a site
 *
 * ## Input Handling for GET Requests
 *
 * Read-only abilities (readonly: true) use GET requests. WordPress REST API does NOT
 * auto-parse JSON from query strings, so:
 *
 * - Omit `?input` parameter entirely to use schema defaults (recommended)
 * - Use `?input=` (empty) which also triggers defaults
 * - DO NOT use `?input=%7B%7D` (URL-encoded JSON) - it arrives as a string and fails validation
 *
 * Our input schemas use `'type' => array('object', 'null')` with defaults, so callers
 * can simply call the endpoint without any input parameter.
 *
 * @see .mwpdev/docs/abilities-api-docs/known-issues.md for detailed explanation
 */
class MainWP_Abilities_Sites {

    /**
     * Register all site abilities.
     *
     * @return void
     */
    public static function register(): void {
        if ( ! function_exists( 'wp_register_ability' ) ) {
            return;
        }

        self::register_list_sites();
        self::register_get_site();
        self::register_sync_sites();
        self::register_get_site_plugins();
        self::register_get_site_themes();
    }

    /**
     * Register mainwp/list-sites-v1 ability.
     *
     * @return void
     */
    private static function register_list_sites(): void {
        wp_register_ability(
            'mainwp/list-sites-v1',
            array(
                'label'               => __( 'List MainWP Sites', 'mainwp' ),
                'description'         => __( 'List MainWP child sites with pagination and filtering. Returns basic site information including ID, URL, name, and connection status.', 'mainwp' ),
                'category'            => 'mainwp-sites',
                'input_schema'        => self::get_list_sites_input_schema(),
                'output_schema'       => self::get_list_sites_output_schema(),
                'execute_callback'    => array( self::class, 'execute_list_sites' ),
                'permission_callback' => array( MainWP_Abilities_Util::class, 'check_rest_api_permission' ),
                'meta'                => array(
                    'show_in_rest' => true,
                    'annotations'  => array(
                        'instructions' => '',
                        'readonly'     => true,
                        'destructive'  => false,
                        'idempotent'   => true,
                    ),
                ),
            )
        );
    }

    /**
     * Register mainwp/get-site-v1 ability.
     *
     * @return void
     */
    private static function register_get_site(): void {
        wp_register_ability(
            'mainwp/get-site-v1',
            array(
                'label'               => __( 'Get MainWP Site', 'mainwp' ),
                'description'         => __( 'Get detailed information about a single MainWP child site.', 'mainwp' ),
                'category'            => 'mainwp-sites',
                'input_schema'        => self::get_get_site_input_schema(),
                'output_schema'       => self::get_site_output_schema(),
                'execute_callback'    => array( self::class, 'execute_get_site' ),
                'permission_callback' => MainWP_Abilities_Util::make_site_permission_callback( 'site_id_or_domain' ),
                'meta'                => array(
                    'show_in_rest' => true,
                    'annotations'  => array(
                        'instructions' => '',
                        'readonly'     => false, // Uses POST because site_id_or_domain is required (can't pass via GET query string).
                        'destructive'  => false,
                        'idempotent'   => true,
                    ),
                ),
            )
        );
    }

    /**
     * Register mainwp/sync-sites-v1 ability.
     *
     * @return void
     */
    private static function register_sync_sites(): void {
        wp_register_ability(
            'mainwp/sync-sites-v1',
            array(
                'label'               => __( 'Sync MainWP Sites', 'mainwp' ),
                'description'         => __( 'Trigger synchronization for one or more child sites. Operations with >50 sites are automatically queued for background processing.', 'mainwp' ),
                'category'            => 'mainwp-sites',
                'input_schema'        => self::get_sync_sites_input_schema(),
                'output_schema'       => self::get_sync_output_schema(),
                'execute_callback'    => array( self::class, 'execute_sync_sites' ),
                'permission_callback' => array( MainWP_Abilities_Util::class, 'check_manage_sites_permission' ),
                'meta'                => array(
                    'show_in_rest' => true,
                    'annotations'  => array(
                        'instructions' => 'Operations with >50 sites are queued and return job_id.',
                        'readonly'     => false,
                        'destructive'  => false,
                        'idempotent'   => true,
                    ),
                ),
            )
        );
    }

    /**
     * Register mainwp/get-site-plugins-v1 ability.
     *
     * @return void
     */
    private static function register_get_site_plugins(): void {
        wp_register_ability(
            'mainwp/get-site-plugins-v1',
            array(
                'label'               => __( 'Get Site Plugins', 'mainwp' ),
                'description'         => __( 'Get list of plugins installed on a child site.', 'mainwp' ),
                'category'            => 'mainwp-sites',
                'input_schema'        => self::get_site_plugins_input_schema(),
                'output_schema'       => self::get_plugins_output_schema(),
                'execute_callback'    => array( self::class, 'execute_get_site_plugins' ),
                'permission_callback' => MainWP_Abilities_Util::make_site_permission_callback( 'site_id_or_domain' ),
                'meta'                => array(
                    'show_in_rest' => true,
                    'annotations'  => array(
                        'instructions' => '',
                        'readonly'     => false, // Uses POST because site_id_or_domain is required (can't pass via GET query string).
                        'destructive'  => false,
                        'idempotent'   => true,
                    ),
                ),
            )
        );
    }

    /**
     * Register mainwp/get-site-themes-v1 ability.
     *
     * @return void
     */
    private static function register_get_site_themes(): void {
        wp_register_ability(
            'mainwp/get-site-themes-v1',
            array(
                'label'               => __( 'Get Site Themes', 'mainwp' ),
                'description'         => __( 'Get list of themes installed on a child site.', 'mainwp' ),
                'category'            => 'mainwp-sites',
                'input_schema'        => self::get_site_themes_input_schema(),
                'output_schema'       => self::get_themes_output_schema(),
                'execute_callback'    => array( self::class, 'execute_get_site_themes' ),
                'permission_callback' => MainWP_Abilities_Util::make_site_permission_callback( 'site_id_or_domain' ),
                'meta'                => array(
                    'show_in_rest' => true,
                    'annotations'  => array(
                        'instructions' => '',
                        'readonly'     => false, // Uses POST because site_id_or_domain is required (can't pass via GET query string).
                        'destructive'  => false,
                        'idempotent'   => true,
                    ),
                ),
            )
        );
    }

    // =========================================================================
    // Input Schema Definitions
    // =========================================================================

    /**
     * Get input schema for list-sites-v1.
     *
     * Note: Uses 'type' => array('object', 'null') to allow callers to omit the input
     * parameter entirely on GET requests. All properties have defaults, so no input
     * is required. See class docblock for GET request input handling details.
     *
     * @return array
     */
    public static function get_list_sites_input_schema(): array {
        return array(
            'type'                 => array( 'object', 'null' ),
            'properties'           => array(
                'page'      => array(
                    'type'        => 'integer',
                    'description' => __( 'Page number (1-based).', 'mainwp' ),
                    'default'     => 1,
                    'minimum'     => 1,
                ),
                'per_page'  => array(
                    'type'        => 'integer',
                    'description' => __( 'Items per page.', 'mainwp' ),
                    'default'     => 20,
                    'minimum'     => 1,
                    'maximum'     => 100,
                ),
                'status'    => array(
                    'type'        => 'string',
                    'description' => __( 'Filter by connection status.', 'mainwp' ),
                    'enum'        => array( 'any', 'connected', 'disconnected', 'suspended' ),
                    'default'     => 'any',
                ),
                'search'    => array(
                    'type'        => 'string',
                    'description' => __( 'Search term for site name or URL.', 'mainwp' ),
                    'default'     => '',
                ),
                'client_id' => array(
                    'type'        => 'integer',
                    'description' => __( 'Filter by client ID.', 'mainwp' ),
                    'minimum'     => 1,
                ),
                'tag_id'    => array(
                    'type'        => 'integer',
                    'description' => __( 'Filter by tag/group ID.', 'mainwp' ),
                    'minimum'     => 1,
                ),
            ),
            'additionalProperties' => false,
        );
    }

    /**
     * Get input schema for get-site-v1.
     *
     * Note: We use type: ["integer", "string"] instead of oneOf because JSON Schema
     * validators fail when a numeric string like "123" matches multiple oneOf branches.
     *
     * @return array
     */
    private static function get_get_site_input_schema(): array {
        return array(
            'type'                 => 'object',
            'properties'           => array(
                'site_id_or_domain' => array(
                    'type'        => array( 'integer', 'string' ),
                    'description' => __( 'Site ID (integer) or domain/URL (string).', 'mainwp' ),
                ),
                'include_stats'     => array(
                    'type'        => 'boolean',
                    'description' => __( 'Include site statistics (updates count, health, etc.).', 'mainwp' ),
                    'default'     => false,
                ),
            ),
            'required'             => array( 'site_id_or_domain' ),
            'additionalProperties' => false,
        );
    }

    /**
     * Get input schema for sync-sites-v1.
     *
     * @return array
     */
    private static function get_sync_sites_input_schema(): array {
        return array(
            'type'                 => array( 'object', 'null' ),
            'properties'           => array(
                'site_ids_or_domains' => array(
                    'type'        => 'array',
                    'description' => __( 'Site IDs or domains to sync. Empty array means all sites.', 'mainwp' ),
                    'items'       => array(
                        'type' => array( 'integer', 'string' ),
                    ),
                    'default'     => array(),
                ),
            ),
            'additionalProperties' => false,
        );
    }

    /**
     * Get input schema for get-site-plugins-v1.
     *
     * @return array
     */
    private static function get_site_plugins_input_schema(): array {
        return array(
            'type'                 => 'object',
            'properties'           => array(
                'site_id_or_domain' => array(
                    'type'        => array( 'integer', 'string' ),
                    'description' => __( 'Site ID or domain/URL.', 'mainwp' ),
                ),
                'status'            => array(
                    'type'        => 'string',
                    'enum'        => array( 'all', 'active', 'inactive' ),
                    'default'     => 'all',
                    'description' => __( 'Filter by plugin status.', 'mainwp' ),
                ),
                'has_update'        => array(
                    'type'        => 'boolean',
                    'description' => __( 'Filter to only plugins with available updates.', 'mainwp' ),
                ),
            ),
            'required'             => array( 'site_id_or_domain' ),
            'additionalProperties' => false,
        );
    }

    /**
     * Get input schema for get-site-themes-v1.
     *
     * @return array
     */
    private static function get_site_themes_input_schema(): array {
        return array(
            'type'                 => 'object',
            'properties'           => array(
                'site_id_or_domain' => array(
                    'type'        => array( 'integer', 'string' ),
                    'description' => __( 'Site ID or domain/URL.', 'mainwp' ),
                ),
                'status'            => array(
                    'type'        => 'string',
                    'enum'        => array( 'all', 'active', 'inactive' ),
                    'default'     => 'all',
                    'description' => __( 'Filter by theme status.', 'mainwp' ),
                ),
                'has_update'        => array(
                    'type'        => 'boolean',
                    'description' => __( 'Filter to only themes with available updates.', 'mainwp' ),
                ),
            ),
            'required'             => array( 'site_id_or_domain' ),
            'additionalProperties' => false,
        );
    }

    // =========================================================================
    // Output Schema Definitions
    // =========================================================================

    /**
     * Get output schema for list-sites-v1.
     *
     * @return array
     */
    public static function get_list_sites_output_schema(): array {
        return array(
            'type'       => 'object',
            'properties' => array(
                'items'    => array(
                    'type'        => 'array',
                    'description' => __( 'List of sites.', 'mainwp' ),
                    'items'       => array(
                        'type'                 => 'object',
                        'properties'           => array(
                            'id'        => array(
                                'type'        => 'integer',
                                'description' => __( 'MainWP site ID.', 'mainwp' ),
                            ),
                            'url'       => array(
                                'type'        => 'string',
                                'format'      => 'uri',
                                'description' => __( 'Site URL.', 'mainwp' ),
                            ),
                            'name'      => array(
                                'type'        => 'string',
                                'description' => __( 'Site name or label.', 'mainwp' ),
                            ),
                            'status'    => array(
                                'type'        => 'string',
                                'enum'        => array( 'connected', 'disconnected', 'suspended' ),
                                'description' => __( 'Connection status.', 'mainwp' ),
                            ),
                            'client_id' => array(
                                'oneOf'       => array(
                                    array(
                                        'type'    => 'integer',
                                        'minimum' => 1,
                                    ),
                                    array( 'type' => 'null' ),
                                ),
                                'description' => __( 'Associated client ID if any.', 'mainwp' ),
                            ),
                        ),
                        'required'             => array( 'id', 'url', 'name', 'status' ),
                        'additionalProperties' => false,
                    ),
                ),
                'page'     => array(
                    'type'        => 'integer',
                    'description' => __( 'Current page number.', 'mainwp' ),
                ),
                'per_page' => array(
                    'type'        => 'integer',
                    'description' => __( 'Items per page.', 'mainwp' ),
                ),
                'total'    => array(
                    'type'        => 'integer',
                    'description' => __( 'Total number of sites matching filters.', 'mainwp' ),
                ),
            ),
            'required'   => array( 'items', 'page', 'per_page', 'total' ),
        );
    }

    /**
     * Get output schema for get-site-v1.
     *
     * @return array
     */
    private static function get_site_output_schema(): array {
        return array(
            'type'       => 'object',
            'properties' => array(
                'id'             => array(
                    'type'        => 'integer',
                    'description' => __( 'MainWP site ID.', 'mainwp' ),
                ),
                'url'            => array(
                    'type'        => 'string',
                    'format'      => 'uri',
                    'description' => __( 'Site URL.', 'mainwp' ),
                ),
                'name'           => array(
                    'type'        => 'string',
                    'description' => __( 'Site name or label.', 'mainwp' ),
                ),
                'status'         => array(
                    'type'        => 'string',
                    'enum'        => array( 'connected', 'disconnected', 'suspended' ),
                    'description' => __( 'Connection status.', 'mainwp' ),
                ),
                'client_id'      => array(
                    'oneOf'       => array(
                        array(
                            'type'    => 'integer',
                            'minimum' => 1,
                        ),
                        array( 'type' => 'null' ),
                    ),
                    'description' => __( 'Associated client ID.', 'mainwp' ),
                ),
                'wp_version'     => array(
                    'type'        => 'string',
                    'description' => __( 'WordPress version.', 'mainwp' ),
                ),
                'php_version'    => array(
                    'type'        => 'string',
                    'description' => __( 'PHP version.', 'mainwp' ),
                ),
                'last_sync'      => array(
                    'oneOf'       => array(
                        array(
                            'type'   => 'string',
                            'format' => 'date-time',
                        ),
                        array( 'type' => 'null' ),
                    ),
                    'description' => __( 'Last successful sync timestamp (ISO 8601).', 'mainwp' ),
                ),
                'admin_username' => array(
                    'type'        => 'string',
                    'description' => __( 'Admin username for child site.', 'mainwp' ),
                ),
                'child_version'  => array(
                    'type'        => 'string',
                    'description' => __( 'MainWP Child plugin version.', 'mainwp' ),
                ),
                'notes'          => array(
                    'type'        => 'string',
                    'description' => __( 'Site notes.', 'mainwp' ),
                ),
                'stats'          => array(
                    'type'        => 'object',
                    'description' => __( 'Site statistics (only if include_stats=true).', 'mainwp' ),
                    'properties'  => array(
                        'plugin_updates'      => array( 'type' => 'integer' ),
                        'theme_updates'       => array( 'type' => 'integer' ),
                        'wp_update_available' => array( 'type' => 'boolean' ),
                        'health_score'        => array(
                            'oneOf' => array(
                                array(
                                    'type'    => 'integer',
                                    'minimum' => 0,
                                    'maximum' => 100,
                                ),
                                array( 'type' => 'null' ),
                            ),
                        ),
                    ),
                ),
            ),
            'required'   => array( 'id', 'url', 'name', 'status' ),
        );
    }

    /**
     * Get output schema for sync-sites-v1.
     *
     * @return array
     */
    private static function get_sync_output_schema(): array {
        return array(
            'type'       => 'object',
            'properties' => array(
                // Immediate execution response (≤50 sites).
                'synced'       => array(
                    'type'        => 'array',
                    'description' => __( 'Sites successfully synced (immediate mode only).', 'mainwp' ),
                    'items'       => array(
                        'type'       => 'object',
                        'properties' => array(
                            'id'   => array( 'type' => 'integer' ),
                            'url'  => array(
                                'type'   => 'string',
                                'format' => 'uri',
                            ),
                            'name' => array( 'type' => 'string' ),
                        ),
                        'required'   => array( 'id', 'url', 'name' ),
                    ),
                ),
                'errors'       => array(
                    'type'        => 'array',
                    'description' => __( 'Sites that failed to sync (immediate mode only).', 'mainwp' ),
                    'items'       => array(
                        'type'       => 'object',
                        'properties' => array(
                            'identifier' => array(
                                'oneOf' => array(
                                    array( 'type' => 'integer' ),
                                    array( 'type' => 'string' ),
                                ),
                            ),
                            'code'       => array( 'type' => 'string' ),
                            'message'    => array( 'type' => 'string' ),
                        ),
                        'required'   => array( 'identifier', 'code', 'message' ),
                    ),
                ),
                'total_synced' => array(
                    'type'        => 'integer',
                    'description' => __( 'Number of sites successfully synced.', 'mainwp' ),
                ),
                'total_errors' => array(
                    'type'        => 'integer',
                    'description' => __( 'Number of sites that failed to sync.', 'mainwp' ),
                ),
                // Queued execution response (>50 sites).
                'queued'       => array(
                    'type'        => 'boolean',
                    'description' => __( 'Whether the operation was queued for background processing.', 'mainwp' ),
                ),
                'job_id'       => array(
                    'type'        => 'string',
                    'description' => __( 'Background job ID for status polling (only when queued=true).', 'mainwp' ),
                ),
                'status_url'   => array(
                    'type'        => 'string',
                    'format'      => 'uri',
                    'description' => __( 'URL to poll for job status (only when queued=true).', 'mainwp' ),
                ),
                'sites_queued' => array(
                    'type'        => 'integer',
                    'description' => __( 'Number of sites queued for sync (only when queued=true).', 'mainwp' ),
                ),
            ),
            // Note: Either (synced, errors, total_synced, total_errors) OR (queued, job_id, status_url, sites_queued) will be present.
            'required'   => array(),
        );
    }

    /**
     * Get output schema for get-site-plugins-v1.
     *
     * @return array
     */
    private static function get_plugins_output_schema(): array {
        return array(
            'type'       => 'object',
            'properties' => array(
                'site_id'  => array(
                    'type'        => 'integer',
                    'description' => __( 'MainWP site ID.', 'mainwp' ),
                ),
                'site_url' => array(
                    'type'        => 'string',
                    'format'      => 'uri',
                    'description' => __( 'Site URL.', 'mainwp' ),
                ),
                'plugins'  => array(
                    'type'        => 'array',
                    'description' => __( 'List of plugins.', 'mainwp' ),
                    'items'       => array(
                        'type'       => 'object',
                        'properties' => array(
                            'slug'           => array( 'type' => 'string' ),
                            'name'           => array( 'type' => 'string' ),
                            'version'        => array( 'type' => 'string' ),
                            'active'         => array( 'type' => 'boolean' ),
                            'update_version' => array(
                                'oneOf' => array(
                                    array( 'type' => 'string' ),
                                    array( 'type' => 'null' ),
                                ),
                            ),
                        ),
                        'required'   => array( 'slug', 'name', 'version', 'active' ),
                    ),
                ),
                'total'    => array(
                    'type'        => 'integer',
                    'description' => __( 'Total number of plugins.', 'mainwp' ),
                ),
            ),
            'required'   => array( 'site_id', 'site_url', 'plugins', 'total' ),
        );
    }

    /**
     * Get output schema for get-site-themes-v1.
     *
     * @return array
     */
    private static function get_themes_output_schema(): array {
        return array(
            'type'       => 'object',
            'properties' => array(
                'site_id'      => array(
                    'type'        => 'integer',
                    'description' => __( 'MainWP site ID.', 'mainwp' ),
                ),
                'site_url'     => array(
                    'type'        => 'string',
                    'format'      => 'uri',
                    'description' => __( 'Site URL.', 'mainwp' ),
                ),
                'active_theme' => array(
                    'type'        => 'string',
                    'description' => __( 'Currently active theme slug.', 'mainwp' ),
                ),
                'themes'       => array(
                    'type'        => 'array',
                    'description' => __( 'List of themes.', 'mainwp' ),
                    'items'       => array(
                        'type'       => 'object',
                        'properties' => array(
                            'slug'           => array( 'type' => 'string' ),
                            'name'           => array( 'type' => 'string' ),
                            'version'        => array( 'type' => 'string' ),
                            'active'         => array( 'type' => 'boolean' ),
                            'update_version' => array(
                                'oneOf' => array(
                                    array( 'type' => 'string' ),
                                    array( 'type' => 'null' ),
                                ),
                            ),
                        ),
                        'required'   => array( 'slug', 'name', 'version', 'active' ),
                    ),
                ),
                'total'        => array(
                    'type'        => 'integer',
                    'description' => __( 'Total number of themes.', 'mainwp' ),
                ),
            ),
            'required'   => array( 'site_id', 'site_url', 'active_theme', 'themes', 'total' ),
        );
    }

    // =========================================================================
    // Execute Callbacks
    // =========================================================================

    /**
     * Execute callback for mainwp/list-sites-v1.
     *
     * @param array|null $input Validated input from Abilities API.
     * @return array|\WP_Error
     */
    public static function execute_list_sites( $input ) {
        $input = is_array( $input ) ? $input : array();
        $db    = MainWP_DB::instance();

        // Map ability input to DB method parameters.
        $db_params = array(
            'paged'          => $input['page'] ?? 1,
            'items_per_page' => $input['per_page'] ?? 20,
            's'              => $input['search'] ?? '',
            // Include status-related fields for format_site_for_output().
            'fields'         => array( 'suspended', 'offline_check_result', 'sync_errors' ),
        );

        // Status mapping: 'any' means no filter, otherwise wrap in array.
        // Note: 'connected' filter adds 'unsuspended' to exclude suspended sites,
        // ensuring returned items have consistent status='connected' in output.
        $status = $input['status'] ?? 'any';
        if ( 'any' !== $status ) {
            $db_status = array( $status );
            // When filtering for 'connected', also exclude suspended sites.
            if ( 'connected' === $status ) {
                $db_status[] = 'unsuspended';
            }
            $db_params['status'] = $db_status;
        }

        // Client filter.
        if ( isset( $input['client_id'] ) ) {
            $db_params['client'] = (string) $input['client_id'];
        }

        // Tag/group filter (map tag_id to group_id for DB).
        if ( isset( $input['tag_id'] ) ) {
            $db_params['group_id'] = $input['tag_id'];
        }

        // First get total count (without pagination) to know actual total.
        // Use same filters but without pagination limits.
        $count_params = array_merge(
            $db_params,
            array(
                'paged'          => 1,
                'items_per_page' => 99999, // Large number to get all matching sites.
            )
        );
        $all_websites = $db->get_websites_for_current_user( $count_params );
        $total        = is_array( $all_websites ) ? count( $all_websites ) : 0;

        // Now get the paginated subset.
        $websites = $db->get_websites_for_current_user( $db_params );

        if ( is_wp_error( $websites ) ) {
            return $websites;
        }

        // Map DB records to output schema shape.
        $items = array();
        if ( $websites ) {
            foreach ( $websites as $site ) {
                $items[] = MainWP_Abilities_Util::format_site_for_output( $site );
            }
        }

        return array(
            'items'    => $items,
            'page'     => $input['page'] ?? 1,
            'per_page' => $input['per_page'] ?? 20,
            'total'    => (int) $total,
        );
    }

    /**
     * Execute callback for mainwp/get-site-v1.
     *
     * NOTE: Site resolution and ACL check are performed in permission_callback
     * via make_site_permission_callback(). If we reach this execute callback,
     * permissions have already been verified.
     *
     * @param array $input Validated input from Abilities API.
     * @return array|\WP_Error
     */
    public static function execute_get_site( array $input ) {
        $site = MainWP_Abilities_Util::resolve_site( $input['site_id_or_domain'] );

        if ( is_wp_error( $site ) ) {
            // This shouldn't happen if permission_callback passed, but handle gracefully.
            return $site;
        }

        $include_stats = $input['include_stats'] ?? false;

        return MainWP_Abilities_Util::format_site_for_output( $site, true, $include_stats );
    }

    /**
     * Execute callback for mainwp/sync-sites-v1.
     *
     * @param array|null $input Validated input from Abilities API.
     * @return array|\WP_Error
     */
    public static function execute_sync_sites( $input ) { // phpcs:ignore -- NOSONAR - complexity method.
        $input               = is_array( $input ) ? $input : array();
        $site_ids_or_domains = $input['site_ids_or_domains'] ?? array();

        // If empty, get all sites for current user.
        if ( empty( $site_ids_or_domains ) ) {
            $all_sites = MainWP_DB::instance()->get_websites_for_current_user( array( 'selectgroups' => false ) );

            // Handle potential errors from DB query.
            if ( is_wp_error( $all_sites ) ) {
                return $all_sites;
            }

            $site_ids_or_domains = array_map(
                function ( $s ) {
                    return (int) $s->id;
                },
                $all_sites ? $all_sites : array()
            );
        }

        // Check per-site ACLs and filter to allowed sites.
        $access_check = MainWP_Abilities_Util::check_batch_site_access( $site_ids_or_domains, $input );

        // Queue if > 50 sites.
        if ( count( $access_check['allowed'] ) > 50 ) {
            $job_id = MainWP_Abilities_Util::queue_batch_sync( $access_check['allowed'] );

            // Handle queue failure.
            if ( is_wp_error( $job_id ) ) {
                return $job_id;
            }

            return array(
                'queued'       => true,
                'job_id'       => $job_id,
                'status_url'   => rest_url( "mainwp/v2/jobs/{$job_id}" ),
                'sites_queued' => count( $access_check['allowed'] ),
            );
        }

        // Immediate execution for ≤ 50 sites.
        $synced = array();
        $errors = $access_check['denied']; // Start with ACL-denied sites.

        foreach ( $access_check['allowed'] as $site ) {
            // Check if site is known offline before attempting sync.
            if ( isset( $site->offline_check_result ) && -1 === (int) $site->offline_check_result ) {
                $errors[] = array(
                    'identifier' => (int) $site->id,
                    'code'       => 'mainwp_site_offline',
                    'message'    => __( 'Site is known to be offline.', 'mainwp' ),
                );
                continue;
            }

            // Check child version before sync.
            $version_check = MainWP_Abilities_Util::check_child_version( $site );
            if ( is_wp_error( $version_check ) ) {
                $errors[] = array(
                    'identifier' => (int) $site->id,
                    'code'       => $version_check->get_error_code(),
                    'message'    => $version_check->get_error_message(),
                );
                continue;
            }

            // Perform sync using MainWP_Sync class.
            try {
                $result = MainWP_Sync::sync_site( $site );

                // Allow filtering of sync result for testing/extension purposes.
                $result = apply_filters( 'mainwp_sync_site_result', $result, (int) $site->id );

                if ( is_wp_error( $result ) ) {
                    $errors[] = array(
                        'identifier' => (int) $site->id,
                        'code'       => $result->get_error_code(),
                        'message'    => $result->get_error_message(),
                    );
                } elseif ( false === $result ) {
                    $errors[] = array(
                        'identifier' => (int) $site->id,
                        'code'       => 'mainwp_sync_failed',
                        'message'    => __( 'Sync operation failed.', 'mainwp' ),
                    );
                } else {
                    $synced[] = array(
                        'id'   => (int) $site->id,
                        'url'  => $site->url,
                        'name' => $site->name,
                    );
                }
            } catch ( \Exception $e ) {
                $errors[] = array(
                    'identifier' => (int) $site->id,
                    'code'       => 'mainwp_sync_exception',
                    'message'    => $e->getMessage(),
                );
            }
        }

        return array(
            'queued'       => false,
            'synced'       => $synced,
            'errors'       => $errors,
            'total_synced' => count( $synced ),
            'total_errors' => count( $errors ),
        );
    }

    /**
     * Execute callback for mainwp/get-site-plugins-v1.
     *
     * @param array $input Validated input from Abilities API.
     * @return array|\WP_Error
     */
    public static function execute_get_site_plugins( array $input ) {
        $site = MainWP_Abilities_Util::resolve_site( $input['site_id_or_domain'] );

        if ( is_wp_error( $site ) ) {
            return $site;
        }

        // ACL check is done in permission_callback, but double-check for safety.
        if ( ! MainWP_Abilities_Util::can_access_site( $site, $input ) ) {
            return new \WP_Error(
                'mainwp_access_denied',
                __( 'You do not have permission to access this site.', 'mainwp' ),
                array( 'status' => 403 )
            );
        }

        // Check child version for plugin data.
        $version_check = MainWP_Abilities_Util::check_child_version( $site );
        if ( is_wp_error( $version_check ) ) {
            return $version_check;
        }

        // Get plugins from site data (stored as JSON).
        $plugins_data = ! empty( $site->plugins ) ? json_decode( $site->plugins, true ) : array();
        if ( ! is_array( $plugins_data ) ) {
            $plugins_data = array();
        }

        // Get plugin updates for this site.
        $plugin_updates = ! empty( $site->plugin_upgrades ) ? json_decode( $site->plugin_upgrades, true ) : array();
        if ( ! is_array( $plugin_updates ) ) {
            $plugin_updates = array();
        }

        $status_filter     = $input['status'] ?? 'all';
        $has_update_filter = $input['has_update'] ?? null;

        $plugins = array();
        foreach ( $plugins_data as $slug => $plugin ) {
            $is_active = ! empty( $plugin['active'] );

            // Apply status filter.
            if ( 'active' === $status_filter && ! $is_active ) {
                continue;
            }
            if ( 'inactive' === $status_filter && $is_active ) {
                continue;
            }

            $has_update = isset( $plugin_updates[ $slug ] );

            // Apply has_update filter if specified.
            if ( null !== $has_update_filter && $has_update_filter && ! $has_update ) {
                continue;
            }

            // Extract update version from plugin_upgrades structure.
            $update_version = null;
            if ( $has_update && isset( $plugin_updates[ $slug ]['update']['new_version'] ) ) {
                $update_version = $plugin_updates[ $slug ]['update']['new_version'];
            } elseif ( $has_update && isset( $plugin_updates[ $slug ]['new_version'] ) ) {
                // Alternative structure.
                $update_version = $plugin_updates[ $slug ]['new_version'];
            }

            $plugins[] = array(
                'slug'           => $slug,
                'name'           => $plugin['name'] ?? $slug,
                'version'        => $plugin['version'] ?? '',
                'active'         => $is_active,
                'update_version' => $update_version,
            );
        }

        return array(
            'site_id'  => (int) $site->id,
            'site_url' => $site->url,
            'plugins'  => $plugins,
            'total'    => count( $plugins ),
        );
    }

    /**
     * Execute callback for mainwp/get-site-themes-v1.
     *
     * @param array $input Validated input from Abilities API.
     * @return array|\WP_Error
     */
    public static function execute_get_site_themes( array $input ) {
        $site = MainWP_Abilities_Util::resolve_site( $input['site_id_or_domain'] );

        if ( is_wp_error( $site ) ) {
            return $site;
        }

        // ACL check is done in permission_callback, but double-check for safety.
        if ( ! MainWP_Abilities_Util::can_access_site( $site, $input ) ) {
            return new \WP_Error(
                'mainwp_access_denied',
                __( 'You do not have permission to access this site.', 'mainwp' ),
                array( 'status' => 403 )
            );
        }

        // Check child version for theme data.
        $version_check = MainWP_Abilities_Util::check_child_version( $site );
        if ( is_wp_error( $version_check ) ) {
            return $version_check;
        }

        // Get themes from site data (stored as JSON).
        $themes_data = ! empty( $site->themes ) ? json_decode( $site->themes, true ) : array();
        if ( ! is_array( $themes_data ) ) {
            $themes_data = array();
        }

        // Get theme updates for this site.
        $theme_updates = ! empty( $site->theme_upgrades ) ? json_decode( $site->theme_upgrades, true ) : array();
        if ( ! is_array( $theme_updates ) ) {
            $theme_updates = array();
        }

        // Determine active theme - check for 'active' flag in theme data.
        $active_theme_slug = '';
        foreach ( $themes_data as $slug => $theme ) {
            if ( ! empty( $theme['active'] ) ) {
                $active_theme_slug = $slug;
                break;
            }
        }

        $status_filter     = $input['status'] ?? 'all';
        $has_update_filter = $input['has_update'] ?? null;

        $themes = array();
        foreach ( $themes_data as $slug => $theme ) {
            $is_active = ( $slug === $active_theme_slug );

            // Apply status filter.
            if ( 'active' === $status_filter && ! $is_active ) {
                continue;
            }
            if ( 'inactive' === $status_filter && $is_active ) {
                continue;
            }

            $has_update = isset( $theme_updates[ $slug ] );

            // Apply has_update filter if specified.
            if ( null !== $has_update_filter && $has_update_filter && ! $has_update ) {
                continue;
            }

            // Extract update version from theme_upgrades structure.
            $update_version = null;
            if ( $has_update && isset( $theme_updates[ $slug ]['update']['new_version'] ) ) {
                $update_version = $theme_updates[ $slug ]['update']['new_version'];
            } elseif ( $has_update && isset( $theme_updates[ $slug ]['new_version'] ) ) {
                // Alternative structure.
                $update_version = $theme_updates[ $slug ]['new_version'];
            }

            $themes[] = array(
                'slug'           => $slug,
                'name'           => $theme['name'] ?? $slug,
                'version'        => $theme['version'] ?? '',
                'active'         => $is_active,
                'update_version' => $update_version,
            );
        }

        return array(
            'site_id'      => (int) $site->id,
            'site_url'     => $site->url,
            'active_theme' => $active_theme_slug,
            'themes'       => $themes,
            'total'        => count( $themes ),
        );
    }
}
