<?php
/**
 * MainWP ActivateSiteTheme Ability Tests
 *
 * Tests for the mainwp/activate-site-theme-v1 ability.
 *
 * @package MainWP\Dashboard\Tests
 */

namespace MainWP\Dashboard\Tests;

/**
 * Tests for mainwp/activate-site-theme-v1 ability.
 *
 * @group abilities
 * @group abilities-sites
 */
class Test_ActivateSiteTheme_Ability extends MainWP_Abilities_Test_Case {

    /**
     * Test that the ability is registered.
     *
     * @return void
     */
    public function test_ability_is_registered() {
        $this->skip_if_no_abilities_api();

        $ability = wp_get_ability( 'mainwp/activate-site-theme-v1' );
        $this->assertNotNull( $ability, 'Ability mainwp/activate-site-theme-v1 should be registered.' );
    }

    /**
     * Test successful execution with valid input.
     *
     * @return void
     */
    public function test_activate_site_theme_returns_expected_structure() {
        $this->skip_if_no_abilities_api();
        $this->set_current_user_as_admin();

        $site_id = $this->create_test_site( [
            'name' => 'Test Site',
            'url'  => 'https://test-activate-site-theme.example.com/',
        ] );

        // Mock child site response to bypass OpenSSL signing with test keys.
        $this->mock_child_site_response( $site_id, [
            'success' => true,
        ] );

        $result = $this->execute_ability( 'mainwp/activate-site-theme-v1', [
            'site_id_or_domain' => $site_id,
            'theme'             => 'twentytwentythree',
        ] );

        $this->assertNotWPError( $result, 'Should return successful result.' );
        $this->assertIsArray( $result, 'Result should be an array.' );
        $this->assertArrayHasKey( 'theme', $result, 'Result should contain theme key.' );
        $this->assertIsArray( $result['theme'], 'Result theme should be an array.' );
        $this->assertArrayHasKey( 'slug', $result['theme'], 'Theme should contain slug key.' );
    }

    /**
     * Test that unauthenticated users are denied.
     *
     * @return void
     */
    public function test_activate_site_theme_requires_authentication() {
        $this->skip_if_no_abilities_api();

        wp_set_current_user( 0 );

		// Expect the "doing it wrong" notice from WP_Ability::execute.
		$this->setExpectedIncorrectUsage( 'WP_Ability::execute' );

        $result = $this->execute_ability( 'mainwp/activate-site-theme-v1', [
            'site_id_or_domain' => 1,
            'theme'             => 'twentytwentythree',
        ] );

        $this->assertWPError( $result, 'Unauthenticated request should return WP_Error.' );
        $this->assertEquals(
            'ability_invalid_permissions',
            $result->get_error_code(),
            'Should return ability_invalid_permissions error code.'
        );
    }

    /**
     * Test that users without manage_options capability are denied.
     *
     * @return void
     */
    public function test_activate_site_theme_requires_manage_options() {
        $this->skip_if_no_abilities_api();

        $this->set_current_user_as_subscriber();

        $this->setExpectedIncorrectUsage( 'WP_Ability::execute' );

        $result = $this->execute_ability( 'mainwp/activate-site-theme-v1', [
            'site_id_or_domain' => 1,
            'theme'             => 'twentytwentythree',
        ] );

        $this->assertWPError( $result, 'Subscriber should be denied.' );
        $this->assertEquals(
            'ability_invalid_permissions',
            $result->get_error_code(),
            'Should return ability_invalid_permissions error code.'
        );
    }

    /**
     * Test input validation rejects invalid values.
     *
     * @return void
     */
    public function test_activate_site_theme_validates_input() {
        $this->skip_if_no_abilities_api();
        $this->set_current_user_as_admin();

        $site_id = $this->create_test_site();

        $result = $this->execute_ability( 'mainwp/activate-site-theme-v1', [
            'site_id_or_domain' => $site_id,
        ] );

        $this->assertWPError( $result, 'Invalid input should return WP_Error.' );
    }

    /**
     * Test that outdated child plugin version returns error.
     *
     * @return void
     */
    public function test_activate_site_theme_requires_minimum_child_version() {
        $this->skip_if_no_abilities_api();
        $this->set_current_user_as_admin();

        // Create site with outdated child version (below 4.0.0 minimum).
        $site_id = $this->create_test_site( [
            'name'    => 'Test Site Outdated Child',
            'url'     => 'https://test-activate-theme-outdated.example.com/',
            'version' => '3.0.0',
        ] );

        $result = $this->execute_ability( 'mainwp/activate-site-theme-v1', [
            'site_id_or_domain' => $site_id,
            'theme'             => 'twentytwentythree',
        ] );

        $this->assertWPError( $result, 'Outdated child version should return WP_Error.' );
        $this->assertEquals(
            'mainwp_child_outdated',
            $result->get_error_code(),
            'Should return mainwp_child_outdated error code.'
        );
    }
}
