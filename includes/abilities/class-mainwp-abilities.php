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
     * @return void
     */
    public static function init(): void {
        // Feature gate: If Abilities API is not available, do nothing.
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
     * This filter callback ensures that requests to wp-abilities/v1/abilities/mainwp/*
     * are authenticated using MainWP's consumer_key/consumer_secret mechanism.
     *
     * @param bool $is_mainwp_api Whether the current request is to a MainWP REST API.
     * @return bool True if this is a MainWP REST API or MainWP ability request.
     */
    public static function include_abilities_in_rest_auth( bool $is_mainwp_api ): bool {
        // Already matched as MainWP API, no need to check further.
        if ( $is_mainwp_api ) {
            return true;
        }

        // Check if this is a request to a MainWP ability.
        if ( empty( $_SERVER['REQUEST_URI'] ) ) {
            return false;
        }

        $rest_prefix = trailingslashit( rest_get_url_prefix() );
        $request_uri = esc_url_raw( wp_unslash( $_SERVER['REQUEST_URI'] ) );

        // Match wp-abilities/v1/abilities/mainwp/* routes.
        return ( false !== strpos( $request_uri, $rest_prefix . 'wp-abilities/v1/abilities/mainwp/' ) );
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

        // NOTE: Extensions register their own abilities in their own codebases.
    }
}
