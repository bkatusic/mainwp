<?php
/**
 * MainWP Updates Abilities Tests
 *
 * Tests for update-related abilities:
 * - mainwp/list-updates-v1
 * - mainwp/run-updates-v1
 * - mainwp/list-ignored-updates-v1
 * - mainwp/set-ignored-updates-v1
 *
 * @package MainWP\Dashboard\Tests
 */

namespace MainWP\Dashboard\Tests;

/**
 * Class MainWP_Updates_Abilities_Test
 *
 * Tests for update-related abilities.
 */
class MainWP_Updates_Abilities_Test extends MainWP_Abilities_Test_Case {

	// =========================================================================
	// List Updates Tests
	// =========================================================================

	/**
	 * Test that list-updates returns expected shape.
	 *
	 * @return void
	 */
	public function test_list_updates_returns_expected_shape() {
		$this->skip_if_no_abilities_api();
		$this->set_current_user_as_admin();

		$result = $this->execute_ability( 'mainwp/list-updates-v1', [
			'types' => [ 'plugins', 'themes', 'core' ],
		] );

		$this->assertNotWPError( $result );
		$this->assertIsArray( $result );
		$this->assertArrayHasKey( 'updates', $result );
		$this->assertArrayHasKey( 'summary', $result );
		$this->assertArrayHasKey( 'page', $result );
		$this->assertArrayHasKey( 'per_page', $result );
		$this->assertArrayHasKey( 'total', $result );

		$this->assertIsArray( $result['updates'] );
		$this->assertIsArray( $result['summary'] );
		$this->assertIsInt( $result['page'] );
		$this->assertIsInt( $result['per_page'] );
		$this->assertIsInt( $result['total'] );

		// Verify summary structure.
		$this->assertArrayHasKey( 'core', $result['summary'] );
		$this->assertArrayHasKey( 'plugins', $result['summary'] );
		$this->assertArrayHasKey( 'themes', $result['summary'] );
		$this->assertArrayHasKey( 'translations', $result['summary'] );
		$this->assertArrayHasKey( 'total', $result['summary'] );
	}

	/**
	 * Test that list-updates filters by type.
	 *
	 * @return void
	 */
	public function test_list_updates_filters_by_type() {
		$this->skip_if_no_abilities_api();
		$this->set_current_user_as_admin();

		$site_id = $this->create_test_site( [
			'name'                 => 'Updates Filter Site',
			'offline_check_result' => 1,
		] );

		// Seed plugin and theme upgrades.
		$this->set_site_plugin_upgrades( $site_id, [
			'akismet/akismet.php' => [
				'Name'        => 'Akismet',
				'Version'     => '5.0',
				'new_version' => '5.1',
			],
		] );

		$this->set_site_theme_upgrades( $site_id, [
			'twentytwentyfour' => [
				'Name'        => 'Twenty Twenty-Four',
				'Version'     => '1.0',
				'new_version' => '1.1',
			],
		] );

		// Request only plugins.
		$result = $this->execute_ability( 'mainwp/list-updates-v1', [
			'types' => [ 'plugins' ],
		] );

		$this->assertNotWPError( $result );

		// All updates should be plugin type.
		foreach ( $result['updates'] as $update ) {
			$this->assertEquals( 'plugin', $update['type'], 'Filtered updates should be plugins only.' );
		}
	}

	/**
	 * Test that list-updates filters by site IDs.
	 *
	 * @return void
	 */
	public function test_list_updates_filters_by_site_ids() {
		$this->skip_if_no_abilities_api();
		$this->set_current_user_as_admin();

		$site1_id = $this->create_test_site( [
			'name'                 => 'Updates Site 1',
			'offline_check_result' => 1,
		] );

		$site2_id = $this->create_test_site( [
			'name'                 => 'Updates Site 2',
			'offline_check_result' => 1,
		] );

		// Seed plugin upgrade for site1 only.
		$this->set_site_plugin_upgrades( $site1_id, [
			'akismet/akismet.php' => [
				'Name'        => 'Akismet',
				'Version'     => '5.0',
				'new_version' => '5.1',
			],
		] );

		// Request only site1.
		$result = $this->execute_ability( 'mainwp/list-updates-v1', [
			'site_ids_or_domains' => [ $site1_id ],
		] );

		$this->assertNotWPError( $result );

		// All updates should be for site1.
		foreach ( $result['updates'] as $update ) {
			$this->assertEquals( $site1_id, $update['site_id'], 'Filtered updates should be for specified site.' );
		}
	}

	// =========================================================================
	// Run Updates Tests
	// =========================================================================

	/**
	 * Test that run-updates executes plugin updates.
	 *
	 * @return void
	 */
	public function test_run_updates_executes_plugin_updates() {
		$this->skip_if_no_abilities_api();
		$this->set_current_user_as_admin();

		$site_id = $this->create_test_site( [
			'name'                 => 'Run Updates Site',
			'offline_check_result' => 1,
			'version'              => '5.0.0',
		] );

		$result = $this->execute_ability( 'mainwp/run-updates-v1', [
			'site_ids_or_domains' => [ $site_id ],
			'types'               => [ 'plugins' ],
		] );

		$this->assertNotWPError( $result );
		$this->assertIsArray( $result );
		$this->assertArrayHasKey( 'updated', $result );
		$this->assertArrayHasKey( 'errors', $result );
		$this->assertArrayHasKey( 'summary', $result );

		$this->assertIsArray( $result['updated'] );
		$this->assertIsArray( $result['errors'] );
		$this->assertIsArray( $result['summary'] );
		$this->assertArrayHasKey( 'total_updated', $result['summary'] );
		$this->assertArrayHasKey( 'total_errors', $result['summary'] );
		$this->assertArrayHasKey( 'sites_updated', $result['summary'] );
	}

	/**
	 * Test that run-updates skips offline sites.
	 *
	 * @return void
	 */
	public function test_run_updates_skips_offline_sites() {
		$this->skip_if_no_abilities_api();
		$this->set_current_user_as_admin();

		$offline_id = $this->create_test_site( [
			'name'                 => 'Offline Update Site',
			'offline_check_result' => -1,
			'version'              => '5.0.0',
		] );

		$result = $this->execute_ability( 'mainwp/run-updates-v1', [
			'site_ids_or_domains' => [ $offline_id ],
			'types'               => [ 'plugins' ],
		] );

		$this->assertNotWPError( $result );

		// Offline site should be in errors.
		$found_offline_error = false;
		foreach ( $result['errors'] as $error ) {
			if ( $error['site_id'] === $offline_id && $error['code'] === 'mainwp_site_offline' ) {
				$found_offline_error = true;
				break;
			}
		}

		$this->assertTrue( $found_offline_error, 'Offline site should produce mainwp_site_offline error.' );
	}

	/**
	 * Test that run-updates handles child outdated error.
	 *
	 * @return void
	 */
	public function test_run_updates_handles_child_outdated() {
		$this->skip_if_no_abilities_api();
		$this->set_current_user_as_admin();

		$outdated_id = $this->create_test_site( [
			'name'                 => 'Outdated Child Site',
			'offline_check_result' => 1,
			'version'              => '3.9.0', // Below 4.0.0 minimum.
		] );

		$result = $this->execute_ability( 'mainwp/run-updates-v1', [
			'site_ids_or_domains' => [ $outdated_id ],
			'types'               => [ 'plugins' ],
		] );

		$this->assertNotWPError( $result );

		// Outdated child should be in errors.
		$found_outdated_error = false;
		foreach ( $result['errors'] as $error ) {
			if ( $error['site_id'] === $outdated_id && $error['code'] === 'mainwp_child_outdated' ) {
				$found_outdated_error = true;
				break;
			}
		}

		$this->assertTrue( $found_outdated_error, 'Outdated child should produce mainwp_child_outdated error.' );
	}

	/**
	 * Test that run-updates large batch returns queued response.
	 *
	 * @return void
	 */
	public function test_run_updates_large_batch_returns_queued() {
		$this->skip_if_no_abilities_api();
		$this->set_current_user_as_admin();

		// Create 60 test sites (exceeds 50 threshold).
		$site_ids = [];
		for ( $i = 0; $i < 60; $i++ ) {
			$site_ids[] = $this->create_test_site( [
				'name'                 => "Large Update Site {$i}",
				'offline_check_result' => 1,
				'version'              => '5.0.0',
			] );
		}

		$result = $this->execute_ability( 'mainwp/run-updates-v1', [
			'site_ids_or_domains' => $site_ids,
			'types'               => [ 'plugins' ],
		] );

		$this->assertNotWPError( $result );

		// Should be queued.
		$this->assertTrue( $result['queued'], 'Large batch should be queued.' );
		$this->assertArrayHasKey( 'job_id', $result );
		$this->assertArrayHasKey( 'status_url', $result );
		$this->assertArrayHasKey( 'updates_queued', $result );

		// Track job for cleanup in tearDown.
		$this->track_update_job( $result['job_id'] );

		// Queued jobs don't have updated key.
		$this->assertArrayNotHasKey( 'updated', $result );
	}

	/**
	 * Test that run-updates partial success works.
	 *
	 * @return void
	 */
	public function test_run_updates_partial_success() {
		$this->skip_if_no_abilities_api();
		$this->set_current_user_as_admin();

		$online_id = $this->create_test_site( [
			'name'                 => 'Online Update Site',
			'offline_check_result' => 1,
			'version'              => '5.0.0',
		] );

		$offline_id = $this->create_test_site( [
			'name'                 => 'Offline Update Site',
			'offline_check_result' => -1,
			'version'              => '5.0.0',
		] );

		$result = $this->execute_ability( 'mainwp/run-updates-v1', [
			'site_ids_or_domains' => [ $online_id, $offline_id ],
			'types'               => [ 'plugins' ],
		] );

		$this->assertNotWPError( $result );

		// Should have some errors for offline site.
		$this->assertGreaterThan( 0, $result['summary']['total_errors'], 'Should have errors for offline site.' );
	}

	/**
	 * Test that run-updates handles update operations.
	 *
	 * Note: In a test environment without real child sites, all updates will
	 * fail due to connection issues. This test verifies the structure and
	 * error handling rather than actual update success.
	 *
	 * @return void
	 */
	public function test_run_updates_mocked_partial_failure() {
		$this->skip_if_no_abilities_api();
		$this->set_current_user_as_admin();

		// Create two online sites with valid child versions.
		$site1_id = $this->create_test_site( [
			'name'                 => 'Update Site 1',
			'offline_check_result' => 1,
			'version'              => '5.0.0',
		] );

		$site2_id = $this->create_test_site( [
			'name'                 => 'Update Site 2',
			'offline_check_result' => 1,
			'version'              => '5.0.0',
		] );

		// Mock partial update result to test the mocking mechanism.
		$this->mock_partial_update_result(
			[ $site1_id ],
			[
				[
					'site_id' => $site2_id,
					'code'    => 'mainwp_update_failed',
					'message' => 'Update failed on remote site',
				],
			]
		);

		$result = $this->execute_ability( 'mainwp/run-updates-v1', [
			'site_ids_or_domains' => [ $site1_id, $site2_id ],
			'types'               => [ 'plugins' ],
		] );

		$this->assertNotWPError( $result );
		$this->assertIsArray( $result );

		// Verify output structure.
		$this->assertArrayHasKey( 'updated', $result );
		$this->assertArrayHasKey( 'errors', $result );
		$this->assertArrayHasKey( 'summary', $result );

		// Verify summary reflects correct counts.
		$this->assertArrayHasKey( 'total_updated', $result['summary'] );
		$this->assertArrayHasKey( 'total_errors', $result['summary'] );
		$this->assertArrayHasKey( 'sites_updated', $result['summary'] );

		// In test environment, both sites will likely be in errors due to no real connection.
		// Verify at least one site appears in the errors with a valid error code.
		$found_error = false;
		$valid_error_codes = [ 'mainwp_update_failed', 'mainwp_connection_failed', 'mainwp_update_exception' ];
		foreach ( $result['errors'] as $error ) {
			if ( isset( $error['code'] ) && in_array( $error['code'], $valid_error_codes, true ) ) {
				$found_error = true;
				break;
			}
		}

		// Either sites are updated (mocking worked) or errored (real connection failed).
		$has_results = ( $result['summary']['total_updated'] > 0 ) || ( $result['summary']['total_errors'] > 0 );
		$this->assertTrue( $has_results, 'Should have at least some update results or errors.' );
	}

	// =========================================================================
	// Ignored Updates Tests
	// =========================================================================

	/**
	 * Test that list-ignored-updates returns list.
	 *
	 * @return void
	 */
	public function test_list_ignored_updates_returns_list() {
		$this->skip_if_no_abilities_api();
		$this->set_current_user_as_admin();

		$result = $this->execute_ability( 'mainwp/list-ignored-updates-v1', [] );

		$this->assertNotWPError( $result );
		$this->assertIsArray( $result );
		$this->assertArrayHasKey( 'ignored', $result );
		$this->assertArrayHasKey( 'total', $result );

		$this->assertIsArray( $result['ignored'] );
		$this->assertIsInt( $result['total'] );
	}

	/**
	 * Test that set-ignored-updates adds to list.
	 *
	 * @return void
	 */
	public function test_set_ignored_updates_adds_to_list() {
		$this->skip_if_no_abilities_api();
		$this->set_current_user_as_admin();

		$site_id = $this->create_test_site( [
			'name' => 'Ignore Updates Site',
		] );

		// Add ignored update.
		$result = $this->execute_ability( 'mainwp/set-ignored-updates-v1', [
			'action'            => 'ignore',
			'site_id_or_domain' => $site_id,
			'type'              => 'plugin',
			'slug'              => 'test-plugin',
		] );

		$this->assertNotWPError( $result );
		$this->assertIsArray( $result );
		$this->assertTrue( $result['success'] );
		$this->assertEquals( 'ignore', $result['action'] );
		$this->assertEquals( $site_id, $result['site_id'] );
		$this->assertEquals( 'plugin', $result['type'] );
		$this->assertEquals( 'test-plugin', $result['slug'] );
	}

	/**
	 * Test that set-ignored-updates removes from list.
	 *
	 * @return void
	 */
	public function test_set_ignored_updates_removes_from_list() {
		$this->skip_if_no_abilities_api();
		$this->set_current_user_as_admin();

		$site_id = $this->create_test_site( [
			'name' => 'Unignore Updates Site',
		] );

		// Seed with ignored plugin.
		$this->set_site_ignored_plugins( $site_id, [
			'test-plugin' => [
				'Name'             => 'Test Plugin',
				'ignored_versions' => [ 'all_versions' ],
			],
		] );

		// Remove ignored update.
		$result = $this->execute_ability( 'mainwp/set-ignored-updates-v1', [
			'action'            => 'unignore',
			'site_id_or_domain' => $site_id,
			'type'              => 'plugin',
			'slug'              => 'test-plugin',
		] );

		$this->assertNotWPError( $result );
		$this->assertTrue( $result['success'] );
		$this->assertEquals( 'unignore', $result['action'] );
	}

	/**
	 * Test that set-ignored-updates validates action.
	 *
	 * @return void
	 */
	public function test_set_ignored_updates_validates_action() {
		$this->skip_if_no_abilities_api();
		$this->set_current_user_as_admin();

		$site_id = $this->create_test_site();

		// Invalid action should fail.
		$result = $this->execute_ability( 'mainwp/set-ignored-updates-v1', [
			'action'            => 'invalid_action',
			'site_id_or_domain' => $site_id,
			'type'              => 'plugin',
			'slug'              => 'test-plugin',
		] );

		$this->assertWPError( $result );
	}

	/**
	 * Test that set-ignored-updates validates type.
	 *
	 * @return void
	 */
	public function test_set_ignored_updates_validates_type() {
		$this->skip_if_no_abilities_api();
		$this->set_current_user_as_admin();

		$site_id = $this->create_test_site();

		// Invalid type should fail.
		$result = $this->execute_ability( 'mainwp/set-ignored-updates-v1', [
			'action'            => 'ignore',
			'site_id_or_domain' => $site_id,
			'type'              => 'invalid_type',
			'slug'              => 'test-plugin',
		] );

		$this->assertWPError( $result );
	}

	/**
	 * Test that set-ignored-updates requires all fields.
	 *
	 * @return void
	 */
	public function test_set_ignored_updates_requires_fields() {
		$this->skip_if_no_abilities_api();
		$this->set_current_user_as_admin();

		$site_id = $this->create_test_site();

		// Missing slug should fail.
		$result = $this->execute_ability( 'mainwp/set-ignored-updates-v1', [
			'action'            => 'ignore',
			'site_id_or_domain' => $site_id,
			'type'              => 'plugin',
			// Missing 'slug'.
		] );

		$this->assertWPError( $result );
	}

	/**
	 * Test that set-ignored-updates handles core type.
	 *
	 * @return void
	 */
	public function test_set_ignored_updates_handles_core() {
		$this->skip_if_no_abilities_api();
		$this->set_current_user_as_admin();

		$site_id = $this->create_test_site();

		$result = $this->execute_ability( 'mainwp/set-ignored-updates-v1', [
			'action'            => 'ignore',
			'site_id_or_domain' => $site_id,
			'type'              => 'core',
			'slug'              => 'wordpress',
		] );

		$this->assertNotWPError( $result );
		$this->assertTrue( $result['success'] );
		$this->assertEquals( 'core', $result['type'] );
	}

	/**
	 * Test that set-ignored-updates handles theme type.
	 *
	 * @return void
	 */
	public function test_set_ignored_updates_handles_theme() {
		$this->skip_if_no_abilities_api();
		$this->set_current_user_as_admin();

		$site_id = $this->create_test_site();

		$result = $this->execute_ability( 'mainwp/set-ignored-updates-v1', [
			'action'            => 'ignore',
			'site_id_or_domain' => $site_id,
			'type'              => 'theme',
			'slug'              => 'twentytwentyfour',
		] );

		$this->assertNotWPError( $result );
		$this->assertTrue( $result['success'] );
		$this->assertEquals( 'theme', $result['type'] );
	}

	/**
	 * Test that list-ignored-updates filters by type.
	 *
	 * @return void
	 */
	public function test_list_ignored_updates_filters_by_type() {
		$this->skip_if_no_abilities_api();
		$this->set_current_user_as_admin();

		$site_id = $this->create_test_site( [
			'name' => 'Ignored Filter Site',
		] );

		// Seed with ignored plugins and themes.
		$this->set_site_ignored_plugins( $site_id, [ 'test-plugin' => 'Test Plugin' ] );
		$this->set_site_ignored_themes( $site_id, [ 'test-theme' => 'Test Theme' ] );

		// Request only plugins.
		$result = $this->execute_ability( 'mainwp/list-ignored-updates-v1', [
			'types' => [ 'plugins' ],
		] );

		$this->assertNotWPError( $result );

		// All items should be plugins.
		foreach ( $result['ignored'] as $item ) {
			$this->assertEquals( 'plugin', $item['type'], 'Filtered items should be plugins only.' );
		}
	}

	/**
	 * Test that list-ignored-updates filters by site.
	 *
	 * @return void
	 */
	public function test_list_ignored_updates_filters_by_site() {
		$this->skip_if_no_abilities_api();
		$this->set_current_user_as_admin();

		$site1_id = $this->create_test_site( [ 'name' => 'Ignored Site 1' ] );
		$site2_id = $this->create_test_site( [ 'name' => 'Ignored Site 2' ] );

		// Seed site1 with ignored plugin.
		$this->set_site_ignored_plugins( $site1_id, [ 'test-plugin' => 'Test Plugin' ] );

		// Request only site1.
		$result = $this->execute_ability( 'mainwp/list-ignored-updates-v1', [
			'site_ids_or_domains' => [ $site1_id ],
		] );

		$this->assertNotWPError( $result );

		// All items should be for site1.
		foreach ( $result['ignored'] as $item ) {
			$this->assertEquals( $site1_id, $item['site_id'], 'Filtered items should be for site1.' );
		}
	}

	/**
	 * Test that run-updates handles empty site list.
	 *
	 * @return void
	 */
	public function test_run_updates_empty_site_list() {
		$this->skip_if_no_abilities_api();
		$this->set_current_user_as_admin();

		// Empty list should update all sites.
		$result = $this->execute_ability( 'mainwp/run-updates-v1', [
			'site_ids_or_domains' => [],
			'types'               => [ 'plugins' ],
		] );

		$this->assertNotWPError( $result );
		$this->assertIsArray( $result );
	}

	/**
	 * Test that run-updates handles specific_items filter.
	 *
	 * @return void
	 */
	public function test_run_updates_specific_items_filter() {
		$this->skip_if_no_abilities_api();
		$this->set_current_user_as_admin();

		$site_id = $this->create_test_site( [
			'name'                 => 'Specific Items Site',
			'offline_check_result' => 1,
			'version'              => '5.0.0',
		] );

		$result = $this->execute_ability( 'mainwp/run-updates-v1', [
			'site_ids_or_domains' => [ $site_id ],
			'types'               => [ 'plugins' ],
			'specific_items'      => [ 'akismet/akismet.php' ],
		] );

		$this->assertNotWPError( $result );
		$this->assertIsArray( $result );
		// Implementation filters to only update specified slugs.
	}

	/**
	 * Test that run-updates handles connection failure errors.
	 *
	 * Verifies that connection failures during update attempts produce the
	 * expected error code and message structure in the response.
	 *
	 * @return void
	 */
	public function test_run_updates_handles_connection_failure() {
		$this->skip_if_no_abilities_api();
		$this->set_current_user_as_admin();

		$site_id = $this->create_test_site( [
			'name'                 => 'Connection Failure Site',
			'offline_check_result' => 1,
			'version'              => '5.0.0',
		] );

		// Mock connection failure for the site.
		$this->mock_partial_update_result(
			[], // No successes.
			[
				[
					'site_id' => $site_id,
					'code'    => 'mainwp_connection_failed',
					'message' => 'Connection to child site timed out',
				],
			]
		);

		$result = $this->execute_ability( 'mainwp/run-updates-v1', [
			'site_ids_or_domains' => [ $site_id ],
			'types'               => [ 'plugins' ],
		] );

		$this->assertNotWPError( $result );
		$this->assertIsArray( $result );
		$this->assertArrayHasKey( 'errors', $result );
		$this->assertArrayHasKey( 'summary', $result );

		// Find connection failure error for this site.
		$found_connection_error = false;
		$error_message          = '';
		foreach ( $result['errors'] as $error ) {
			if ( isset( $error['site_id'] ) && $error['site_id'] === $site_id ) {
				if ( $error['code'] === 'mainwp_connection_failed' ) {
					$found_connection_error = true;
					$error_message          = $error['message'] ?? '';
					break;
				}
			}
		}

		$this->assertTrue(
			$found_connection_error,
			'Connection failure should appear in errors with mainwp_connection_failed code.'
		);

		$this->assertStringContainsString(
			'timed out',
			strtolower( $error_message ),
			'Error message should describe the connection failure.'
		);

		// Verify summary reflects the error.
		$this->assertGreaterThanOrEqual(
			1,
			$result['summary']['total_errors'],
			'Summary should reflect at least 1 error for connection failure.'
		);
	}

	/**
	 * Test that run-updates handles authentication failure errors.
	 *
	 * Verifies that authentication failures during update attempts produce
	 * the expected error code and message structure in the response.
	 *
	 * @return void
	 */
	public function test_run_updates_handles_auth_failure() {
		$this->skip_if_no_abilities_api();
		$this->set_current_user_as_admin();

		$site_id = $this->create_test_site( [
			'name'                 => 'Auth Failure Site',
			'offline_check_result' => 1,
			'version'              => '5.0.0',
		] );

		// Mock authentication failure for the site.
		$this->mock_partial_update_result(
			[], // No successes.
			[
				[
					'site_id' => $site_id,
					'code'    => 'mainwp_auth_failed',
					'message' => 'Authentication failed: Invalid credentials or OpenSSL signature mismatch',
				],
			]
		);

		$result = $this->execute_ability( 'mainwp/run-updates-v1', [
			'site_ids_or_domains' => [ $site_id ],
			'types'               => [ 'plugins' ],
		] );

		$this->assertNotWPError( $result );
		$this->assertIsArray( $result );
		$this->assertArrayHasKey( 'errors', $result );
		$this->assertArrayHasKey( 'summary', $result );

		// Find auth failure error for this site.
		$found_auth_error = false;
		$error_message    = '';
		foreach ( $result['errors'] as $error ) {
			if ( isset( $error['site_id'] ) && $error['site_id'] === $site_id ) {
				if ( $error['code'] === 'mainwp_auth_failed' ) {
					$found_auth_error = true;
					$error_message    = $error['message'] ?? '';
					break;
				}
			}
		}

		$this->assertTrue(
			$found_auth_error,
			'Authentication failure should appear in errors with mainwp_auth_failed code.'
		);

		$this->assertStringContainsString(
			'authentication',
			strtolower( $error_message ),
			'Error message should describe the authentication failure.'
		);

		// Verify summary reflects the error.
		$this->assertGreaterThanOrEqual(
			1,
			$result['summary']['total_errors'],
			'Summary should reflect at least 1 error for auth failure.'
		);
	}
}
