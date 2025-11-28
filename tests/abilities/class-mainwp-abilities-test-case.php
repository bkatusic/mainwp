<?php
/**
 * MainWP Abilities Test Case Base Class
 *
 * @package MainWP\Dashboard\Tests
 */

namespace MainWP\Dashboard\Tests;

use WP_UnitTestCase;

/**
 * Base class for MainWP Abilities tests.
 *
 * Provides helper methods for creating test fixtures and mocking MainWP behavior.
 */
abstract class MainWP_Abilities_Test_Case extends WP_UnitTestCase {

	/**
	 * Track created sync job IDs for cleanup.
	 *
	 * @var array
	 */
	protected $created_sync_job_ids = [];

	/**
	 * Track created update job IDs for cleanup.
	 *
	 * @var array
	 */
	protected $created_update_job_ids = [];

	/**
	 * Set up test environment.
	 *
	 * @return void
	 */
	public function setUp(): void {
		parent::setUp();

		// Reset job ID tracking.
		$this->created_sync_job_ids   = [];
		$this->created_update_job_ids = [];

		// Ensure abilities are registered.
		\MainWP\Dashboard\MainWP_Abilities::init();
		do_action( 'wp_abilities_api_categories_init' );
		do_action( 'wp_abilities_api_init' );
	}

	/**
	 * Clean up test sites after each test.
	 *
	 * @return void
	 */
	public function tearDown(): void {
		global $wpdb;

		// Clean up test sites.
		$wpdb->query( "DELETE FROM {$wpdb->prefix}mainwp_wp WHERE url LIKE 'https://test-%'" );

		// Clean up job transients created during tests.
		foreach ( $this->created_sync_job_ids as $job_id ) {
			delete_transient( 'mainwp_sync_job_' . $job_id );
			wp_unschedule_hook( 'mainwp_process_sync_job', [ $job_id ] );
		}

		foreach ( $this->created_update_job_ids as $job_id ) {
			delete_transient( 'mainwp_update_job_' . $job_id );
			wp_unschedule_hook( 'mainwp_process_update_job', [ $job_id ] );
		}

		// Remove any mock filters.
		remove_all_filters( 'mainwp_sync_site_result' );
		remove_all_filters( 'mainwp_run_update_result' );
		remove_all_filters( 'mainwp_check_site_access' );

		parent::tearDown();
	}

	/**
	 * Create a test site in the MainWP database.
	 *
	 * @param array $args Optional. Site properties to override defaults.
	 * @return int Site ID.
	 */
	protected function create_test_site( array $args = [] ): int {
		global $wpdb;

		$defaults = [
			'url'                  => 'https://test-' . wp_generate_uuid4() . '.example.com/',
			'name'                 => 'Test Site',
			'adminname'            => 'admin',
			'pubkey'               => 'test-pubkey',
			'privkey'              => 'test-privkey',
			'verify_method'        => 1,
			'ssl_version'          => 0,
			'http_user'            => '',
			'http_pass'            => '',
			'suspended'            => 0,
			'offline_check_result' => 1, // 1 = online, -1 = offline
			'sync_errors'          => '',
			'client_id'            => 0,
			'version'              => '5.0.0', // Child plugin version
		];

		// Format specifiers matching the column types.
		$formats = [
			'url'                  => '%s',
			'name'                 => '%s',
			'adminname'            => '%s',
			'pubkey'               => '%s',
			'privkey'              => '%s',
			'verify_method'        => '%d',
			'ssl_version'          => '%d',
			'http_user'            => '%s',
			'http_pass'            => '%s',
			'suspended'            => '%d',
			'offline_check_result' => '%d',
			'sync_errors'          => '%s',
			'client_id'            => '%d',
			'version'              => '%s',
		];

		$data = array_merge( $defaults, $args );

		// Build format array in same order as data keys.
		$format_array = [];
		foreach ( array_keys( $data ) as $key ) {
			$format_array[] = $formats[ $key ] ?? '%s';
		}

		$wpdb->insert(
			$wpdb->prefix . 'mainwp_wp',
			$data,
			$format_array
		);

		return (int) $wpdb->insert_id;
	}

	/**
	 * Set current user as an admin with MainWP permissions.
	 *
	 * @return int User ID.
	 */
	protected function set_current_user_as_admin(): int {
		$user_id = $this->factory->user->create( [ 'role' => 'administrator' ] );
		wp_set_current_user( $user_id );

		// Grant MainWP API access if required.
		update_user_meta( $user_id, 'mainwp_api_enabled', 1 );

		return $user_id;
	}

	/**
	 * Mock a sync failure for specific site(s).
	 *
	 * When site IDs are provided, only those sites will fail; others will
	 * return their original result. When no site IDs are provided (or null),
	 * all sites will fail (backwards compatible behavior).
	 *
	 * @param string         $error_code    Error code to return.
	 * @param string         $error_message Error message to return.
	 * @param int|array|null $site_ids      Optional. Site ID or array of site IDs to fail.
	 *                                      If null, all sites fail.
	 * @return void
	 */
	protected function mock_sync_failure( string $error_code, string $error_message, $site_ids = null ): void {
		// Normalize to array if single ID provided.
		if ( null !== $site_ids && ! is_array( $site_ids ) ) {
			$site_ids = [ $site_ids ];
		}

		add_filter(
			'mainwp_sync_site_result',
			function ( $result, $site_id ) use ( $error_code, $error_message, $site_ids ) {
				// If no specific site IDs provided, fail all sites.
				if ( null === $site_ids ) {
					return new \WP_Error( $error_code, $error_message );
				}

				// Only fail if site_id is in the list.
				if ( in_array( (int) $site_id, array_map( 'intval', $site_ids ), true ) ) {
					return new \WP_Error( $error_code, $error_message );
				}

				// Return original result for non-targeted sites.
				return $result;
			},
			10,
			2
		);
	}

	/**
	 * Mock a partial update result for testing batch operations.
	 *
	 * @param array $successes Array of successful site IDs.
	 * @param array $failures  Array of failed site IDs with error info.
	 * @return void
	 */
	protected function mock_partial_update_result( array $successes, array $failures ): void {
		add_filter(
			'mainwp_run_update_result',
			function ( $result, $site_id ) use ( $successes, $failures ) {
				if ( in_array( $site_id, $successes, true ) ) {
					return [ 'success' => true ];
				}
				foreach ( $failures as $failure ) {
					if ( $failure['site_id'] === $site_id ) {
						return new \WP_Error( $failure['code'], $failure['message'] );
					}
				}
				return $result;
			},
			10,
			2
		);
	}

	/**
	 * Skip test if Abilities API is not available.
	 *
	 * @return void
	 */
	protected function skip_if_no_abilities_api(): void {
		if ( ! function_exists( 'wp_get_ability' ) ) {
			$this->markTestSkipped( 'Abilities API not available.' );
		}
	}

	/**
	 * Get a test site by ID.
	 *
	 * @param int $site_id Site ID.
	 * @return object|null Site object or null if not found.
	 */
	protected function get_test_site( int $site_id ): ?object {
		global $wpdb;

		$site = $wpdb->get_row(
			$wpdb->prepare(
				"SELECT * FROM {$wpdb->prefix}mainwp_wp WHERE id = %d",
				$site_id
			)
		);

		return $site ?: null;
	}

	/**
	 * Execute an ability by name with given input.
	 *
	 * @param string $ability_name Ability name (e.g., 'mainwp/list-sites-v1').
	 * @param array  $input        Input parameters for the ability.
	 * @return mixed Ability execution result.
	 */
	protected function execute_ability( string $ability_name, array $input = [] ) {
		if ( ! function_exists( 'wp_get_ability' ) ) {
			return new \WP_Error( 'abilities_unavailable', 'Abilities API not available.' );
		}

		$ability = wp_get_ability( $ability_name );
		if ( ! $ability ) {
			return new \WP_Error( 'ability_not_found', "Ability {$ability_name} not found." );
		}

		return $ability->execute( $input );
	}

	/**
	 * Track a sync job ID for cleanup in tearDown.
	 *
	 * @param string $job_id Sync job ID.
	 * @return void
	 */
	protected function track_sync_job( string $job_id ): void {
		$this->created_sync_job_ids[] = $job_id;
	}

	/**
	 * Track an update job ID for cleanup in tearDown.
	 *
	 * @param string $job_id Update job ID.
	 * @return void
	 */
	protected function track_update_job( string $job_id ): void {
		$this->created_update_job_ids[] = $job_id;
	}

	// =========================================================================
	// Site Data Seeding Helpers
	// =========================================================================

	/**
	 * Set plugins data for a test site.
	 *
	 * Seeds the 'plugins' column in mainwp_wp table with the given plugin data.
	 * This is the installed plugins list, not the upgrades list.
	 *
	 * @param int   $site_id Site ID.
	 * @param array $plugins Array of plugin data. Each plugin should have keys like:
	 *                       'Name', 'Version', 'active' (0 or 1), etc.
	 *                       Array keys should be plugin slugs (e.g., 'akismet/akismet.php').
	 * @return void
	 */
	protected function set_site_plugins( int $site_id, array $plugins ): void {
		global $wpdb;

		$wpdb->update(
			$wpdb->prefix . 'mainwp_wp',
			[ 'plugins' => wp_json_encode( $plugins ) ],
			[ 'id' => $site_id ],
			[ '%s' ],
			[ '%d' ]
		);
	}

	/**
	 * Set plugin upgrades data for a test site.
	 *
	 * Seeds the 'plugin_upgrades' column in mainwp_wp table.
	 * This is the list of available plugin updates.
	 *
	 * @param int   $site_id  Site ID.
	 * @param array $upgrades Array of plugin upgrade data. Each upgrade should have:
	 *                        'Name', 'Version' (current), 'new_version', etc.
	 *                        Array keys should be plugin slugs.
	 * @return void
	 */
	protected function set_site_plugin_upgrades( int $site_id, array $upgrades ): void {
		global $wpdb;

		$wpdb->update(
			$wpdb->prefix . 'mainwp_wp',
			[ 'plugin_upgrades' => wp_json_encode( $upgrades ) ],
			[ 'id' => $site_id ],
			[ '%s' ],
			[ '%d' ]
		);
	}

	/**
	 * Set themes data for a test site.
	 *
	 * Seeds the 'themes' column in mainwp_wp table with the given theme data.
	 * This is the installed themes list, not the upgrades list.
	 *
	 * @param int   $site_id Site ID.
	 * @param array $themes  Array of theme data. Each theme should have keys like:
	 *                       'Name', 'Version', 'active' (0 or 1), etc.
	 *                       Array keys should be theme slugs (e.g., 'twentytwentyfour').
	 * @return void
	 */
	protected function set_site_themes( int $site_id, array $themes ): void {
		global $wpdb;

		$wpdb->update(
			$wpdb->prefix . 'mainwp_wp',
			[ 'themes' => wp_json_encode( $themes ) ],
			[ 'id' => $site_id ],
			[ '%s' ],
			[ '%d' ]
		);
	}

	/**
	 * Set theme upgrades data for a test site.
	 *
	 * Seeds the 'theme_upgrades' column in mainwp_wp table.
	 * This is the list of available theme updates.
	 *
	 * @param int   $site_id  Site ID.
	 * @param array $upgrades Array of theme upgrade data. Each upgrade should have:
	 *                        'Name', 'Version' (current), 'new_version', etc.
	 *                        Array keys should be theme slugs.
	 * @return void
	 */
	protected function set_site_theme_upgrades( int $site_id, array $upgrades ): void {
		global $wpdb;

		$wpdb->update(
			$wpdb->prefix . 'mainwp_wp',
			[ 'theme_upgrades' => wp_json_encode( $upgrades ) ],
			[ 'id' => $site_id ],
			[ '%s' ],
			[ '%d' ]
		);
	}

	/**
	 * Set ignored plugins data for a test site.
	 *
	 * Seeds the 'ignored_plugins' column in mainwp_wp table.
	 *
	 * @param int   $site_id Site ID.
	 * @param array $ignored Array of ignored plugin data. Keys are plugin slugs,
	 *                       values are plugin info arrays with 'Name', 'ignored_versions', etc.
	 * @return void
	 */
	protected function set_site_ignored_plugins( int $site_id, array $ignored ): void {
		global $wpdb;

		$wpdb->update(
			$wpdb->prefix . 'mainwp_wp',
			[ 'ignored_plugins' => wp_json_encode( $ignored ) ],
			[ 'id' => $site_id ],
			[ '%s' ],
			[ '%d' ]
		);
	}

	/**
	 * Set ignored themes data for a test site.
	 *
	 * Seeds the 'ignored_themes' column in mainwp_wp table.
	 *
	 * @param int   $site_id Site ID.
	 * @param array $ignored Array of ignored theme data. Keys are theme slugs,
	 *                       values are theme info arrays with 'Name', 'ignored_versions', etc.
	 * @return void
	 */
	protected function set_site_ignored_themes( int $site_id, array $ignored ): void {
		global $wpdb;

		$wpdb->update(
			$wpdb->prefix . 'mainwp_wp',
			[ 'ignored_themes' => wp_json_encode( $ignored ) ],
			[ 'id' => $site_id ],
			[ '%s' ],
			[ '%d' ]
		);
	}

	/**
	 * Set WordPress core upgrade info for a test site.
	 *
	 * Seeds the 'wp_upgrades' column in mainwp_wp table.
	 *
	 * @param int   $site_id  Site ID.
	 * @param array $upgrades WordPress core upgrade data with 'current', 'new_version', etc.
	 * @return void
	 */
	protected function set_site_wp_upgrades( int $site_id, array $upgrades ): void {
		global $wpdb;

		$wpdb->update(
			$wpdb->prefix . 'mainwp_wp',
			[ 'wp_upgrades' => wp_json_encode( $upgrades ) ],
			[ 'id' => $site_id ],
			[ '%s' ],
			[ '%d' ]
		);
	}

	/**
	 * Set a site option via MainWP's wp_options table.
	 *
	 * Uses the same pattern as MainWP_DB::update_website_option() but
	 * directly for tests without needing the full DB class.
	 *
	 * @param int    $site_id Site ID.
	 * @param string $option  Option name.
	 * @param mixed  $value   Option value (will be serialized if needed).
	 * @return void
	 */
	protected function set_site_option( int $site_id, string $option, $value ): void {
		global $wpdb;

		$table = $wpdb->prefix . 'mainwp_wp_options';

		// Check if option exists.
		$exists = $wpdb->get_var(
			$wpdb->prepare(
				"SELECT COUNT(*) FROM {$table} WHERE wpid = %d AND name = %s",
				$site_id,
				$option
			)
		);

		$serialized = is_scalar( $value ) ? $value : maybe_serialize( $value );

		if ( $exists ) {
			$wpdb->update(
				$table,
				[ 'value' => $serialized ],
				[
					'wpid' => $site_id,
					'name' => $option,
				],
				[ '%s' ],
				[ '%d', '%s' ]
			);
		} else {
			$wpdb->insert(
				$table,
				[
					'wpid'  => $site_id,
					'name'  => $option,
					'value' => $serialized,
				],
				[ '%d', '%s', '%s' ]
			);
		}
	}
}
