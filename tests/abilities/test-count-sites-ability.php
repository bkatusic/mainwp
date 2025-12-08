<?php
/**
 * MainWP CountSites Ability Tests
 *
 * Tests for the mainwp/count-sites-v1 ability.
 *
 * @package MainWP\Dashboard\Tests
 */

namespace MainWP\Dashboard\Tests;

/**
 * Tests for mainwp/count-sites-v1 ability.
 *
 * @group abilities
 * @group abilities-sites
 */
class Test_CountSites_Ability extends MainWP_Abilities_Test_Case {

    /**
     * Test that the ability is registered.
     *
     * @return void
     */
    public function test_ability_is_registered() {
        $this->skip_if_no_abilities_api();

        $ability = wp_get_ability( 'mainwp/count-sites-v1' );
        $this->assertNotNull( $ability, 'Ability mainwp/count-sites-v1 should be registered.' );
    }

    /**
     * Test successful execution with valid input.
     *
     * @return void
     */
    public function test_count_sites_returns_expected_structure() {
        $this->skip_if_no_abilities_api();
        $this->set_current_user_as_admin();

        $site_id = $this->create_test_site( [
            'name' => 'Test Site',
            'url'  => 'https://test-count-sites.example.com/',
        ] );

        $result = $this->execute_ability( 'mainwp/count-sites-v1', [] );

        $this->assertNotWPError( $result, 'Should return successful result.' );
        $this->assertIsArray( $result );
        $this->assertArrayHasKey('total', $result);
        $this->assertIsInt($result['total']);
    }

    /**
     * Test that unauthenticated users are denied.
     *
     * @return void
     */
    public function test_count_sites_requires_authentication() {
        $this->skip_if_no_abilities_api();

        wp_set_current_user( 0 );

		// Expect the "doing it wrong" notice from WP_Ability::execute.
		$this->setExpectedIncorrectUsage( 'WP_Ability::execute' );

        $result = $this->execute_ability( 'mainwp/count-sites-v1', [] );

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
    public function test_count_sites_requires_manage_options() {
        $this->skip_if_no_abilities_api();

        $this->set_current_user_as_subscriber();

        $this->setExpectedIncorrectUsage( 'WP_Ability::execute' );

        $result = $this->execute_ability( 'mainwp/count-sites-v1', [] );

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
    public function test_count_sites_validates_input() {
        $this->skip_if_no_abilities_api();
        $this->set_current_user_as_admin();

        $site_id = $this->create_test_site();

        $result = $this->execute_ability( 'mainwp/count-sites-v1', [
            'status' => 'invalid_status', // Invalid enum value
        ] );

        $this->assertWPError( $result, 'Invalid input should return WP_Error.' );
    }
}
