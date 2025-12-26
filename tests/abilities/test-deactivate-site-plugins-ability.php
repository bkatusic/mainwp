<?php
/**
 * MainWP DeactivateSitePlugins Ability Tests
 *
 * Tests for the mainwp/deactivate-site-plugins-v1 ability.
 *
 * @package MainWP\Dashboard\Tests
 */

namespace MainWP\Dashboard\Tests;

/**
 * Tests for mainwp/deactivate-site-plugins-v1 ability.
 *
 * @group abilities
 * @group abilities-sites
 */
class Test_DeactivateSitePlugins_Ability extends MainWP_Abilities_Test_Case {

    /**
     * Test that the ability is registered.
     *
     * @return void
     */
    public function test_ability_is_registered() {
        $this->skip_if_no_abilities_api();

        $ability = wp_get_ability( 'mainwp/deactivate-site-plugins-v1' );
        $this->assertNotNull( $ability, 'Ability mainwp/deactivate-site-plugins-v1 should be registered.' );
    }

    /**
     * Test successful execution with valid input.
     *
     * @return void
     */
    public function test_deactivate_site_plugins_returns_expected_structure() {
        $this->skip_if_no_abilities_api();
        $this->set_current_user_as_admin();

        $site_id = $this->create_test_site( [
            'name' => 'Test Site',
            'url'  => 'https://test-deactivate-site-plugins.example.com/',
        ] );

        // Mock child site response to bypass OpenSSL signing with test keys.
        $this->mock_child_site_response( $site_id, [
            'plugin' => [
                'akismet/akismet.php' => true,
            ],
        ] );

        $result = $this->execute_ability( 'mainwp/deactivate-site-plugins-v1', [
            'site_id_or_domain' => $site_id,
            'plugins'           => ['akismet/akismet.php'],
        ] );

        $this->assertNotWPError( $result, 'Should return successful result.' );
        $this->assertIsArray( $result );
        $this->assertArrayHasKey('deactivated', $result);
        $this->assertArrayHasKey('errors', $result);
        $this->assertIsArray($result['deactivated']);
    }

    /**
     * Test that unauthenticated users are denied.
     *
     * @return void
     */
    public function test_deactivate_site_plugins_requires_authentication() {
        $this->skip_if_no_abilities_api();

        wp_set_current_user( 0 );

		// Expect the "doing it wrong" notice from WP_Ability::execute.
		$this->setExpectedIncorrectUsage( 'WP_Ability::execute' );

        $result = $this->execute_ability( 'mainwp/deactivate-site-plugins-v1', [
            'site_id_or_domain' => 1,
            'plugins'           => [ 'hello-dolly/hello.php' ],
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
    public function test_deactivate_site_plugins_requires_manage_options() {
        $this->skip_if_no_abilities_api();

        $this->set_current_user_as_subscriber();

        $this->setExpectedIncorrectUsage( 'WP_Ability::execute' );

        $result = $this->execute_ability( 'mainwp/deactivate-site-plugins-v1', [
            'site_id_or_domain' => 1,
            'plugins'           => [ 'hello-dolly/hello.php' ],
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
    public function test_deactivate_site_plugins_validates_input() {
        $this->skip_if_no_abilities_api();
        $this->set_current_user_as_admin();

        $result = $this->execute_ability( 'mainwp/deactivate-site-plugins-v1', [
            'site_id_or_domain' => '',
        ] );

        $this->assertWPError( $result, 'Invalid input should return WP_Error.' );
    }

    /**
     * Test that outdated child plugin version returns error.
     *
     * @return void
     */
    public function test_deactivate_site_plugins_requires_minimum_child_version() {
        $this->skip_if_no_abilities_api();
        $this->set_current_user_as_admin();

        // Create site with outdated child version (below 4.0.0 minimum).
        $site_id = $this->create_test_site( [
            'name'    => 'Test Site Outdated Child',
            'url'     => 'https://test-deactivate-plugins-outdated.example.com/',
            'version' => '3.0.0',
        ] );

        $result = $this->execute_ability( 'mainwp/deactivate-site-plugins-v1', [
            'site_id_or_domain' => $site_id,
            'plugins'           => [ 'akismet/akismet.php' ],
        ] );

        $this->assertWPError( $result, 'Outdated child version should return WP_Error.' );
        $this->assertEquals(
            'mainwp_child_outdated',
            $result->get_error_code(),
            'Should return mainwp_child_outdated error code.'
        );
    }
}
