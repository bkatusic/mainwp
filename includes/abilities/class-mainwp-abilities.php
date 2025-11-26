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

        add_action(
            'wp_abilities_api_categories_init',
            [ static::class, 'register_categories' ]
        );

        add_action(
            'wp_abilities_api_init',
            [ static::class, 'register_abilities' ]
        );
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
            [
                'label'       => __( 'MainWP Sites', 'mainwp' ),
                'description' => __( 'Abilities for managing MainWP child sites including listing, syncing, and monitoring.', 'mainwp' ),
            ]
        );

        // Updates category.
        wp_register_ability_category(
            'mainwp-updates',
            [
                'label'       => __( 'MainWP Updates', 'mainwp' ),
                'description' => __( 'Abilities for managing updates across MainWP child sites including core, plugins, and themes.', 'mainwp' ),
            ]
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
