<?php
/**
 * MainWP DeleteSite Ability Tests
 *
 * Tests for the mainwp/delete-site-v1 ability.
 *
 * @package MainWP\Dashboard\Tests
 */

namespace MainWP\Dashboard\Tests;

/**
 * Tests for mainwp/delete-site-v1 ability.
 *
 * @group abilities
 * @group abilities-sites
 */
class Test_DeleteSite_Ability extends MainWP_Abilities_Test_Case {

    /**
     * Test that the ability is registered.
     *
     * @return void
     */
    public function test_ability_is_registered() {
        $this->skip_if_no_abilities_api();

        $ability = wp_get_ability( 'mainwp/delete-site-v1' );
        $this->assertNotNull( $ability, 'Ability mainwp/delete-site-v1 should be registered.' );
    }

    /**
     * Test successful execution with valid input.
     *
     * @return void
     */
    public function test_delete_site_returns_expected_structure() {
        $this->skip_if_no_abilities_api();
        $this->set_current_user_as_admin();

        $site_id = $this->create_test_site( [
            'name' => 'Test Site',
            'url'  => 'https://test-delete-site.example.com/',
        ] );

        $result = $this->execute_ability( 'mainwp/delete-site-v1', [
            'site_id_or_domain' => $site_id,
            'confirm'           => true,
        ] );

        $this->assertNotWPError( $result, 'Should return successful result.' );
        $this->assertIsArray( $result );
        $this->assertArrayHasKey( 'dry_run', $result );
        $this->assertFalse( $result['dry_run'], 'dry_run should be false for actual deletion.' );
        $this->assertArrayHasKey( 'deleted', $result );
        $this->assertTrue( $result['deleted'], 'deleted should be true.' );
        $this->assertArrayHasKey( 'site', $result );
        $this->assertIsArray( $result['site'] );
        $this->assertEquals( $site_id, $result['site']['id'], 'site.id should match created site.' );
        $this->assertEquals( 'Test Site', $result['site']['name'], 'site.name should match.' );
    }

    /**
     * Test that unauthenticated users are denied.
     *
     * @return void
     */
    public function test_delete_site_requires_authentication() {
        $this->skip_if_no_abilities_api();

        wp_set_current_user( 0 );

		// Expect the "doing it wrong" notice from WP_Ability::execute.
		$this->setExpectedIncorrectUsage( 'WP_Ability::execute' );

        $result = $this->execute_ability( 'mainwp/delete-site-v1', [
            'site_id_or_domain' => 1,
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
    public function test_delete_site_requires_manage_options() {
        $this->skip_if_no_abilities_api();

        $this->set_current_user_as_subscriber();

        $this->setExpectedIncorrectUsage( 'WP_Ability::execute' );

        $result = $this->execute_ability( 'mainwp/delete-site-v1', [
            'site_id_or_domain' => 1,
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
    public function test_delete_site_validates_input() {
        $this->skip_if_no_abilities_api();
        $this->set_current_user_as_admin();

        $result = $this->execute_ability( 'mainwp/delete-site-v1', [
            'site_id_or_domain' => '', // Empty required field
        ] );

        $this->assertWPError( $result, 'Invalid input should return WP_Error.' );
    }

    /**
     * Test that delete-site requires confirmation.
     *
     * @return void
     */
    public function test_delete_site_requires_confirmation() {
        $this->skip_if_no_abilities_api();
        $this->set_current_user_as_admin();

        $site_id = $this->create_test_site( [
            'name' => 'Test Site Without Confirm',
        ] );

        $input = [
            'site_id_or_domain' => $site_id,
            'confirm'           => true,
        ];
        unset( $input['confirm'] );

        $result = $this->execute_ability( 'mainwp/delete-site-v1', $input );

        $this->assertWPError( $result );
        $this->assertEquals( 'mainwp_confirmation_required', $result->get_error_code() );
    }

    /**
     * Test that delete-site dry_run returns preview.
     *
     * @return void
     */
    public function test_delete_site_dry_run_returns_preview() {
        $this->skip_if_no_abilities_api();
        $this->set_current_user_as_admin();

        $site_id = $this->create_test_site( [
            'name' => 'Test Site Dry Run',
        ] );

        $input = [
            'site_id_or_domain' => $site_id,
            'confirm'           => true,
        ];
        unset( $input['confirm'] );
        $input['dry_run'] = true;

        $result = $this->execute_ability( 'mainwp/delete-site-v1', $input );

        $this->assertNotWPError( $result );
        $this->assertIsArray( $result );
        $this->assertArrayHasKey( 'dry_run', $result );
        $this->assertTrue( $result['dry_run'] );
    }

    /**
     * Test that delete-site rejects both dry_run and confirm together.
     *
     * @return void
     */
    public function test_delete_site_rejects_dry_run_and_confirm_together() {
        $this->skip_if_no_abilities_api();
        $this->set_current_user_as_admin();

        $site_id = $this->create_test_site();

        $input = [
            'site_id_or_domain' => $site_id,
            'confirm'           => true,
        ];
        $input['dry_run'] = true;

        $result = $this->execute_ability( 'mainwp/delete-site-v1', $input );

        $this->assertWPError( $result );
        $this->assertEquals( 'mainwp_invalid_input', $result->get_error_code() );
    }
}
