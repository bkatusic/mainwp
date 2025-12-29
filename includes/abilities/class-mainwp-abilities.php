<?php
/**
 * MainWP Abilities API Bootstrap
 *
 * @package MainWP\Dashboard
 */

namespace MainWP\Dashboard;

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

/**
 * Class MainWP_Abilities
 *
 * Bootstraps the Abilities API integration for MainWP Dashboard.
 * Feature-gated: does nothing if Abilities API is not available.
 */
class MainWP_Abilities {

    /**
     * Initialize the Abilities integration.
     *
     * Cron handlers are always initialized to process any previously queued jobs,
     * even if the Abilities API is later disabled. The cron handler constructor
     * is side-effect free beyond hooking actions, so this is safe.
     *
     * @return void
     */
    public static function init(): void {
        // Always initialize cron handlers for batch processing.
        // This ensures previously queued jobs are processed even if Abilities API
        // is disabled after jobs were created. The cron handler only hooks actions
        // and is safe to call without the Abilities API.
        MainWP_Abilities_Cron::instance();

        // Feature gate: If Abilities API is not available, skip ability registration.
        if ( ! function_exists( 'wp_register_ability' ) ) {
            return;
        }

        // Hook into MainWP REST authentication to include Abilities API routes.
        // This allows consumer_key/consumer_secret authentication to work for abilities.
        add_filter(
            'mainwp_rest_is_request_to_rest_api',
            array( static::class, 'include_abilities_in_rest_auth' )
        );

        add_action(
            'wp_abilities_api_categories_init',
            array( static::class, 'register_categories' )
        );

        add_action(
            'wp_abilities_api_init',
            array( static::class, 'register_abilities' )
        );
    }

    /**
     * Include Abilities API routes in MainWP REST authentication.
     *
     * This filter allows MainWP abilities to be accessed via both:
     * 1. MainWP's consumer_key/consumer_secret API authentication
     * 2. WordPress Application Passwords (standard REST auth)
     *
     * For Application Passwords to work, we must NOT force MainWP auth
     * on Abilities API requests, as that would conflict with WP's native auth.
     *
     * @param bool $is_mainwp_api Whether the current request is to a MainWP REST API.
     * @return bool True only for actual MainWP REST API requests (not abilities).
     */
    public static function include_abilities_in_rest_auth( bool $is_mainwp_api ): bool {
        // Pass through the original detection - don't extend MainWP auth to Abilities API.
        // Abilities API has its own permission_callback that checks current_user_can()
        // which works with both MainWP API keys (via MainWP_REST_Authentication) and
        // WordPress Application Passwords (via native WP REST auth).
        return $is_mainwp_api;
    }

    /**
     * Register MainWP ability categories.
     *
     * @return void
     */
    public static function register_categories(): void {
        if ( ! function_exists( 'wp_register_ability_category' ) ) {
            return;
        }

        // Sites category.
        wp_register_ability_category(
            'mainwp-sites',
            array(
                'label'       => __( 'MainWP Sites', 'mainwp' ),
                'description' => __( 'Abilities for managing MainWP child sites including listing, syncing, and monitoring.', 'mainwp' ),
            )
        );

        // Updates category.
        wp_register_ability_category(
            'mainwp-updates',
            array(
                'label'       => __( 'MainWP Updates', 'mainwp' ),
                'description' => __( 'Abilities for managing updates across MainWP child sites including core, plugins, and themes.', 'mainwp' ),
            )
        );

        // Clients category.
        wp_register_ability_category(
            'mainwp-clients',
            array(
                'label'       => __( 'MainWP Clients', 'mainwp' ),
                'description' => __( 'Abilities for managing MainWP clients including creation, updates, and client-site relationships.', 'mainwp' ),
            )
        );

        // Tags category.
        wp_register_ability_category(
            'mainwp-tags',
            array(
                'label'       => __( 'MainWP Tags', 'mainwp' ),
                'description' => __( 'Abilities for managing MainWP tags for organizing sites and clients.', 'mainwp' ),
            )
        );

        // Batch category.
        wp_register_ability_category(
            'mainwp-batch',
            array(
                'label'       => __( 'MainWP Batch Operations', 'mainwp' ),
                'description' => __( 'Abilities for monitoring queued batch operations including sync, update, and site management tasks.', 'mainwp' ),
            )
        );
    }

    /**
     * Register all MainWP abilities.
     *
     * @return void
     */
    public static function register_abilities(): void {
        // Site abilities.
        MainWP_Abilities_Sites::register();

        // Update abilities.
        MainWP_Abilities_Updates::register();

        // Client abilities.
        MainWP_Abilities_Clients::register();

        // Tag abilities.
        MainWP_Abilities_Tags::register();

        // Batch abilities.
        MainWP_Abilities_Batch::register();

        // NOTE: Extensions register their own abilities in their own codebases.
        // Total core abilities: 62 (30 sites + 13 updates + 11 clients + 7 tags + 1 batch).
    }
}
