<?php
/**
 * MainWP DeleteSiteThemes Ability Tests
 *
 * Tests for the mainwp/delete-site-themes-v1 ability.
 *
 * @package MainWP\Dashboard\Tests
 */

namespace MainWP\Dashboard\Tests;

/**
 * Tests for mainwp/delete-site-themes-v1 ability.
 *
 * @group abilities
 * @group abilities-sites
 */
class Test_DeleteSiteThemes_Ability extends MainWP_Abilities_Test_Case {

    /**
     * Test that the ability is registered.
     *
     * @return void
     */
    public function test_ability_is_registered() {
        $this->skip_if_no_abilities_api();

        $ability = wp_get_ability( 'mainwp/delete-site-themes-v1' );
        $this->assertNotNull( $ability, 'Ability mainwp/delete-site-themes-v1 should be registered.' );
    }

    /**
     * Test successful execution with valid input.
     *
     * @return void
     */
    public function test_delete_site_themes_returns_expected_structure() {
        $this->skip_if_no_abilities_api();
        $this->set_current_user_as_admin();

        $site_id = $this->create_test_site( [
            'name' => 'Test Site',
            'url'  => 'https://test-delete-site-themes.example.com/',
        ] );

        $result = $this->execute_ability( 'mainwp/delete-site-themes-v1', [
            'site_id_or_domain' => $site_id,
            'themes'            => ['twentytwenty'],
            'confirm'           => true,
        ] );

        $this->assertNotWPError( $result, 'Should return successful result.' );
        $this->assertIsArray( $result );
        $this->assertArrayHasKey('deleted', $result);
        $this->assertArrayHasKey('errors', $result);
        $this->assertIsArray($result['deleted']);
    }

    /**
     * Test that unauthenticated users are denied.
     *
     * @return void
     */
    public function test_delete_site_themes_requires_authentication() {
        $this->skip_if_no_abilities_api();

        wp_set_current_user( 0 );

		// Expect the "doing it wrong" notice from WP_Ability::execute.
		$this->setExpectedIncorrectUsage( 'WP_Ability::execute' );

        $result = $this->execute_ability( 'mainwp/delete-site-themes-v1', [
            'site_id_or_domain' => 1,
            'themes'            => [ 'twentytwenty' ],
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
    public function test_delete_site_themes_requires_manage_options() {
        $this->skip_if_no_abilities_api();

        $this->set_current_user_as_subscriber();

        $this->setExpectedIncorrectUsage( 'WP_Ability::execute' );

        $result = $this->execute_ability( 'mainwp/delete-site-themes-v1', [
            'site_id_or_domain' => 1,
            'themes'            => [ 'twentytwenty' ],
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
    public function test_delete_site_themes_validates_input() {
        $this->skip_if_no_abilities_api();
        $this->set_current_user_as_admin();

        $site_id = $this->create_test_site();

        $result = $this->execute_ability( 'mainwp/delete-site-themes-v1', [
            'site_id_or_domain' => $site_id,
            'themes'            => 'not-an-array',
        ] );

        $this->assertWPError( $result, 'Invalid input should return WP_Error.' );
    }

    /**
     * Test that delete-site-themes requires confirmation.
     *
     * @return void
     */
    public function test_delete_site_themes_requires_confirmation() {
        $this->skip_if_no_abilities_api();
        $this->set_current_user_as_admin();

        $site_id = $this->create_test_site( [
            'name' => 'Test Site Without Confirm',
        ] );

        $input = [
            'site_id_or_domain' => $site_id,
            'themes'            => ['twentytwenty'],
            'confirm'           => true,
        ];
        unset( $input['confirm'] );

        $result = $this->execute_ability( 'mainwp/delete-site-themes-v1', $input );

        $this->assertWPError( $result );
        $this->assertEquals( 'mainwp_confirmation_required', $result->get_error_code() );
    }

    /**
     * Test that delete-site-themes dry_run returns preview.
     *
     * @return void
     */
    public function test_delete_site_themes_dry_run_returns_preview() {
        $this->skip_if_no_abilities_api();
        $this->set_current_user_as_admin();

        $site_id = $this->create_test_site( [
            'name' => 'Test Site Dry Run',
        ] );

        $input = [
            'site_id_or_domain' => $site_id,
            'themes'            => ['twentytwenty'],
            'confirm'           => true,
        ];
        unset( $input['confirm'] );
        $input['dry_run'] = true;

        $result = $this->execute_ability( 'mainwp/delete-site-themes-v1', $input );

        $this->assertNotWPError( $result );
        $this->assertIsArray( $result );
        $this->assertArrayHasKey( 'dry_run', $result );
        $this->assertTrue( $result['dry_run'] );
        $this->assertArrayHasKey( 'warnings', $result );
        $this->assertIsArray( $result['warnings'] );
        $this->assertArrayHasKey( 'would_affect', $result );
        $this->assertArrayHasKey( 'count', $result );
    }

    /**
     * Test that delete-site-themes rejects both dry_run and confirm together.
     *
     * @return void
     */
    public function test_delete_site_themes_rejects_dry_run_and_confirm_together() {
        $this->skip_if_no_abilities_api();
        $this->set_current_user_as_admin();

        $site_id = $this->create_test_site();

        $input = [
            'site_id_or_domain' => $site_id,
            'themes'            => ['twentytwenty'],
            'confirm'           => true,
        ];
        $input['dry_run'] = true;
        $input['confirm'] = true;

        $result = $this->execute_ability( 'mainwp/delete-site-themes-v1', $input );

        $this->assertWPError( $result );
        $this->assertEquals( 'mainwp_invalid_input', $result->get_error_code() );
    }

    /**
     * Test that dry-run populates warnings when targeting the active theme.
     *
     * @return void
     */
    public function test_delete_site_themes_dry_run_warnings_active() {
        $this->skip_if_no_abilities_api();
        $this->set_current_user_as_admin();

        $site_id = $this->create_test_site( [
            'name' => 'Test Site With Active Theme',
            'url'  => 'https://test-delete-site-themes-warnings.example.com/',
        ] );

        // Set themes data with an active theme.
        $this->set_site_themes( $site_id, [
            'twentytwentyfour' => [
                'Name'    => 'Twenty Twenty-Four',
                'Version' => '1.0',
                'active'  => 1,
                'slug'    => 'twentytwentyfour',
            ],
            'twentytwentythree' => [
                'Name'    => 'Twenty Twenty-Three',
                'Version' => '1.2',
                'active'  => 0,
                'slug'    => 'twentytwentythree',
            ],
        ] );

        $result = $this->execute_ability( 'mainwp/delete-site-themes-v1', [
            'site_id_or_domain' => $site_id,
            'themes'            => [ 'twentytwentyfour' ],
            'dry_run'           => true,
        ] );

        $this->assertNotWPError( $result, 'Dry run should return successful result.' );
        $this->assertIsArray( $result );
        $this->assertTrue( $result['dry_run'] );
        $this->assertArrayHasKey( 'warnings', $result );
        $this->assertIsArray( $result['warnings'] );
        $this->assertGreaterThan( 0, count( $result['warnings'] ), 'Warnings should contain at least one warning for active theme.' );
        $this->assertStringContainsString( 'twentytwentyfour', $result['warnings'][0], 'Warning should mention the active theme slug.' );
    }

    /**
     * Test that dry-run returns empty warnings when targeting inactive themes.
     *
     * @return void
     */
    public function test_delete_site_themes_dry_run_no_warnings_inactive() {
        $this->skip_if_no_abilities_api();
        $this->set_current_user_as_admin();

        $site_id = $this->create_test_site( [
            'name' => 'Test Site With Inactive Theme',
            'url'  => 'https://test-delete-site-themes-no-warnings.example.com/',
        ] );

        // Set themes data with active theme being different from target.
        $this->set_site_themes( $site_id, [
            'twentytwentyfour' => [
                'Name'    => 'Twenty Twenty-Four',
                'Version' => '1.0',
                'active'  => 1,
                'slug'    => 'twentytwentyfour',
            ],
            'twentytwentythree' => [
                'Name'    => 'Twenty Twenty-Three',
                'Version' => '1.2',
                'active'  => 0,
                'slug'    => 'twentytwentythree',
            ],
        ] );

        $result = $this->execute_ability( 'mainwp/delete-site-themes-v1', [
            'site_id_or_domain' => $site_id,
            'themes'            => [ 'twentytwentythree' ],
            'dry_run'           => true,
        ] );

        $this->assertNotWPError( $result, 'Dry run should return successful result.' );
        $this->assertIsArray( $result );
        $this->assertTrue( $result['dry_run'] );
        $this->assertArrayHasKey( 'warnings', $result );
        $this->assertIsArray( $result['warnings'] );
        $this->assertCount( 0, $result['warnings'], 'Warnings should be empty when targeting inactive themes.' );
    }

    /**
     * Test that outdated child plugin version returns error.
     *
     * @return void
     */
    public function test_delete_site_themes_requires_minimum_child_version() {
        $this->skip_if_no_abilities_api();
        $this->set_current_user_as_admin();

        // Create site with outdated child version (below 4.0.0 minimum).
        $site_id = $this->create_test_site( [
            'name'    => 'Test Site Outdated Child',
            'url'     => 'https://test-delete-themes-outdated.example.com/',
            'version' => '3.0.0',
        ] );

        $result = $this->execute_ability( 'mainwp/delete-site-themes-v1', [
            'site_id_or_domain' => $site_id,
            'themes'            => [ 'twentytwenty' ],
            'confirm'           => true,
        ] );

        $this->assertWPError( $result, 'Outdated child version should return WP_Error.' );
        $this->assertEquals(
            'mainwp_child_outdated',
            $result->get_error_code(),
            'Should return mainwp_child_outdated error code.'
        );
    }
}
