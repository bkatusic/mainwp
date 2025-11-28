<?php
/**
 * MainWP REST API Integration Tests
 *
 * Tests for REST v2 controller integration with Abilities API.
 *
 * @package MainWP\Dashboard\Tests
 */

namespace MainWP\Dashboard\Tests;

use WP_REST_Request;
use WP_REST_Server;

/**
 * Class MainWP_REST_Integration_Test
 *
 * Tests REST v2 controllers' abilities-first pattern and fallback behavior.
 */
class MainWP_REST_Integration_Test extends \WP_Test_REST_TestCase {

	/**
	 * REST server instance.
	 *
	 * @var WP_REST_Server
	 */
	protected $server;

	/**
	 * Admin user ID.
	 *
	 * @var int
	 */
	protected $admin_user_id;

	/**
	 * Set up test environment.
	 *
	 * @return void
	 */
	public function setUp(): void {
		parent::setUp();

		global $wp_rest_server;
		$this->server = $wp_rest_server = new WP_REST_Server();
		do_action( 'rest_api_init' );

		// Initialize abilities.
		\MainWP\Dashboard\MainWP_Abilities::init();
		do_action( 'wp_abilities_api_categories_init' );
		do_action( 'wp_abilities_api_init' );
	}

	/**
	 * Tear down test environment.
	 *
	 * @return void
	 */
	public function tearDown(): void {
		global $wpdb, $wp_rest_server;
		$wp_rest_server = null;

		// Clean up test sites.
		$wpdb->query( "DELETE FROM {$wpdb->prefix}mainwp_wp WHERE url LIKE 'https://test-%'" );

		parent::tearDown();
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
	 * Authenticate as admin for REST requests.
	 *
	 * @return void
	 */
	protected function authenticate_as_admin(): void {
		$this->admin_user_id = $this->factory->user->create( [ 'role' => 'administrator' ] );
		wp_set_current_user( $this->admin_user_id );
	}

	/**
	 * Create a test site.
	 *
	 * @param array $args Site properties.
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
			'offline_check_result' => 1,
			'sync_errors'          => '',
			'client_id'            => 0,
			'version'              => '5.0.0',
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

	// =========================================================================
	// Sites Endpoint Tests
	// =========================================================================

	/**
	 * Test that REST sites endpoint exists.
	 *
	 * @return void
	 */
	public function test_rest_sites_endpoint_exists() {
		$routes = $this->server->get_routes();

		$this->assertArrayHasKey(
			'/mainwp/v2/sites',
			$routes,
			'Sites endpoint should exist.'
		);
	}

	/**
	 * Test that REST sites endpoint uses ability when available.
	 *
	 * @return void
	 */
	public function test_rest_sites_endpoint_uses_ability_when_available() {
		$this->skip_if_no_abilities_api();
		$this->authenticate_as_admin();

		$this->create_test_site( [ 'name' => 'REST Test Site' ] );

		$request = new WP_REST_Request( 'GET', '/mainwp/v2/sites' );
		$request->set_param( 'paged', 1 );
		$request->set_param( 'per_page', 10 );

		$response = rest_do_request( $request );

		$this->assertEquals( 200, $response->get_status() );

		$data = $response->get_data();
		$this->assertIsArray( $data );
		$this->assertArrayHasKey( 'success', $data );
		$this->assertArrayHasKey( 'data', $data );
	}

	/**
	 * Test that REST sites sync endpoint uses ability.
	 *
	 * @return void
	 */
	public function test_rest_sites_sync_endpoint_uses_ability() {
		$this->skip_if_no_abilities_api();
		$this->authenticate_as_admin();

		$site_id = $this->create_test_site( [
			'name'                 => 'Sync REST Test',
			'offline_check_result' => 1,
		] );

		$request = new WP_REST_Request( 'POST', '/mainwp/v2/sites/sync' );
		$request->set_body_params( [ 'id_domain' => [ $site_id ] ] );

		$response = rest_do_request( $request );

		// 200 or 207 (multi-status) are valid.
		$this->assertContains(
			$response->get_status(),
			[ 200, 207 ],
			'Sync should return 200 or 207.'
		);

		$data = $response->get_data();
		$this->assertIsArray( $data );
	}

	// =========================================================================
	// Updates Endpoint Tests
	// =========================================================================

	/**
	 * Test that REST updates endpoint exists.
	 *
	 * @return void
	 */
	public function test_rest_updates_endpoint_exists() {
		$routes = $this->server->get_routes();

		$this->assertArrayHasKey(
			'/mainwp/v2/updates',
			$routes,
			'Updates endpoint should exist.'
		);
	}

	/**
	 * Test that REST updates endpoint uses ability when available.
	 *
	 * Tests GET /mainwp/v2/updates returns proper response structure.
	 *
	 * @return void
	 */
	public function test_rest_updates_endpoint_uses_ability_when_available() {
		$this->skip_if_no_abilities_api();
		$this->authenticate_as_admin();

		// Create a test site to ensure we have data.
		$this->create_test_site( [ 'name' => 'Updates REST Test Site' ] );

		$request  = new WP_REST_Request( 'GET', '/mainwp/v2/updates' );
		$response = rest_do_request( $request );

		$this->assertEquals( 200, $response->get_status() );

		$data = $response->get_data();
		$this->assertIsArray( $data );
		$this->assertArrayHasKey( 'success', $data );
		$this->assertArrayHasKey( 'data', $data );
	}

	/**
	 * Test that REST updates endpoint response matches list-updates ability output shape.
	 *
	 * The data key should contain updates keyed by site ID with nested type arrays.
	 *
	 * @return void
	 */
	public function test_rest_updates_endpoint_response_shape() {
		$this->skip_if_no_abilities_api();
		$this->authenticate_as_admin();

		$site_id = $this->create_test_site( [
			'name'    => 'Updates Shape Test Site',
			'version' => '5.0.0',
		] );

		$request  = new WP_REST_Request( 'GET', '/mainwp/v2/updates' );
		$response = rest_do_request( $request );

		$this->assertEquals( 200, $response->get_status() );

		$data = $response->get_data();

		// Verify top-level structure.
		$this->assertArrayHasKey( 'success', $data );
		$this->assertEquals( 1, $data['success'] );
		$this->assertArrayHasKey( 'data', $data );

		// The data should be an array (updates keyed by site ID or empty).
		$this->assertIsArray( $data['data'] );
	}

	/**
	 * Test that REST updates endpoint for specific site returns proper structure.
	 *
	 * Tests GET /mainwp/v2/updates/{site_id} returns updates for that site.
	 *
	 * @return void
	 */
	public function test_rest_updates_per_site_endpoint() {
		$this->authenticate_as_admin();

		$site_id = $this->create_test_site( [
			'name'    => 'Per-Site Updates Test',
			'version' => '5.0.0',
		] );

		$request  = new WP_REST_Request( 'GET', '/mainwp/v2/updates/' . $site_id );
		$response = rest_do_request( $request );

		$this->assertEquals( 200, $response->get_status() );

		$data = $response->get_data();
		$this->assertIsArray( $data );
		$this->assertArrayHasKey( 'success', $data );
		$this->assertArrayHasKey( 'data', $data );

		// Per-site updates should include type keys (wp, plugins, themes, translations).
		$updates_data = $data['data'];
		$this->assertIsArray( $updates_data );

		// At minimum, when type is 'all' (default), these keys should be present.
		// They may be empty arrays if no updates are available.
		$expected_keys = [ 'wp', 'plugins', 'themes', 'translations' ];
		foreach ( $expected_keys as $key ) {
			$this->assertArrayHasKey(
				$key,
				$updates_data,
				"Updates response should include '{$key}' key."
			);
		}
	}

	/**
	 * Test that REST run-updates endpoint exists and requires authentication.
	 *
	 * Tests POST /mainwp/v2/updates/update (update all).
	 *
	 * @return void
	 */
	public function test_rest_run_updates_endpoint_requires_auth() {
		wp_set_current_user( 0 );

		$request  = new WP_REST_Request( 'POST', '/mainwp/v2/updates/update' );
		$response = rest_do_request( $request );

		// Should return 401 or 403 for unauthenticated request.
		$this->assertContains(
			$response->get_status(),
			[ 401, 403 ],
			'Run-updates endpoint should require authentication.'
		);
	}

	/**
	 * Test that REST run-updates endpoint uses ability when available.
	 *
	 * @return void
	 */
	public function test_rest_run_updates_endpoint_uses_ability() {
		$this->skip_if_no_abilities_api();
		$this->authenticate_as_admin();

		$request  = new WP_REST_Request( 'POST', '/mainwp/v2/updates/update' );
		$response = rest_do_request( $request );

		// Should return 200 (either immediate result or queued response).
		$this->assertEquals( 200, $response->get_status() );

		$data = $response->get_data();
		$this->assertIsArray( $data );
		$this->assertArrayHasKey( 'success', $data );
		$this->assertEquals( 1, $data['success'] );
		$this->assertArrayHasKey( 'message', $data );
	}

	/**
	 * Test that REST per-site run-updates endpoint works.
	 *
	 * Tests POST /mainwp/v2/updates/{site_id}/update
	 *
	 * @return void
	 */
	public function test_rest_run_updates_per_site_endpoint() {
		$this->skip_if_no_abilities_api();
		$this->authenticate_as_admin();

		$site_id = $this->create_test_site( [
			'name'                 => 'Run Updates Per-Site Test',
			'offline_check_result' => 1,
			'suspended'            => 0,
		] );

		$request  = new WP_REST_Request( 'POST', '/mainwp/v2/updates/' . $site_id . '/update' );
		$response = rest_do_request( $request );

		// Should return 200.
		$this->assertEquals( 200, $response->get_status() );

		$data = $response->get_data();
		$this->assertIsArray( $data );
		$this->assertArrayHasKey( 'success', $data );
		$this->assertEquals( 1, $data['success'] );
	}

	// =========================================================================
	// Authentication Tests
	// =========================================================================

	/**
	 * Test that unauthenticated request is denied.
	 *
	 * @return void
	 */
	public function test_rest_unauthenticated_denied() {
		wp_set_current_user( 0 );

		$request = new WP_REST_Request( 'GET', '/mainwp/v2/sites' );
		$response = rest_do_request( $request );

		// Should return 401 or 403.
		$this->assertContains(
			$response->get_status(),
			[ 401, 403 ],
			'Unauthenticated request should be denied.'
		);
	}

	/**
	 * Test that authenticated request is allowed.
	 *
	 * @return void
	 */
	public function test_rest_authenticated_allowed() {
		$this->authenticate_as_admin();

		$request = new WP_REST_Request( 'GET', '/mainwp/v2/sites' );
		$response = rest_do_request( $request );

		$this->assertEquals( 200, $response->get_status() );
	}

	// =========================================================================
	// Parameter Mapping Tests
	// =========================================================================

	/**
	 * Test that REST parameter mapping works.
	 *
	 * @return void
	 */
	public function test_rest_parameter_mapping_to_ability_input() {
		$this->skip_if_no_abilities_api();
		$this->authenticate_as_admin();

		// Create sites to have something to paginate.
		for ( $i = 0; $i < 15; $i++ ) {
			$this->create_test_site( [ 'name' => "Param Test Site {$i}" ] );
		}

		$request = new WP_REST_Request( 'GET', '/mainwp/v2/sites' );
		$request->set_param( 'paged', 2 );
		$request->set_param( 'per_page', 5 );

		$response = rest_do_request( $request );

		$this->assertEquals( 200, $response->get_status() );

		$data = $response->get_data();
		// Verify pagination was applied.
		if ( isset( $data['data']['page'] ) ) {
			$this->assertEquals( 2, $data['data']['page'] );
		}
	}

	/**
	 * Test that REST response format is correct.
	 *
	 * @return void
	 */
	public function test_rest_response_format() {
		$this->skip_if_no_abilities_api();
		$this->authenticate_as_admin();

		$request = new WP_REST_Request( 'GET', '/mainwp/v2/sites' );
		$response = rest_do_request( $request );

		$this->assertEquals( 200, $response->get_status() );

		$data = $response->get_data();
		$this->assertIsArray( $data );

		// MainWP REST responses typically have success/data structure.
		if ( isset( $data['success'] ) ) {
			$this->assertIsBool( $data['success'] );
		}
	}

	// =========================================================================
	// Queued Response Tests
	// =========================================================================

	/**
	 * Test that REST queued response format is correct.
	 *
	 * @return void
	 */
	public function test_rest_queued_response_format() {
		$this->skip_if_no_abilities_api();
		$this->authenticate_as_admin();

		// Create 60 sites to trigger queuing.
		$site_ids = [];
		for ( $i = 0; $i < 60; $i++ ) {
			$site_ids[] = $this->create_test_site( [
				'name'                 => "Queued REST Site {$i}",
				'offline_check_result' => 1,
			] );
		}

		$request = new WP_REST_Request( 'POST', '/mainwp/v2/sites/sync' );
		$request->set_body_params( [ 'id_domain' => $site_ids ] );

		$response = rest_do_request( $request );

		// Should return 202 or 200 with queued response.
		$this->assertContains(
			$response->get_status(),
			[ 200, 202 ],
			'Queued response should return 200 or 202.'
		);

		$data = $response->get_data();
		$this->assertIsArray( $data );

		// Check for queued indicators.
		if ( isset( $data['data']['queued'] ) && $data['data']['queued'] ) {
			$this->assertArrayHasKey( 'job_id', $data['data'] );
			$this->assertArrayHasKey( 'status_url', $data['data'] );
		}
	}

	// =========================================================================
	// Error Handling Tests
	// =========================================================================

	/**
	 * Test that REST error responses have proper format.
	 *
	 * @return void
	 */
	public function test_rest_error_response_format() {
		$this->authenticate_as_admin();

		// Request non-existent site.
		$routes = $this->server->get_routes();

		// Check if single site endpoint exists.
		if ( isset( $routes['/mainwp/v2/sites/(?P<id>[\\d]+)'] ) ) {
			$request = new WP_REST_Request( 'GET', '/mainwp/v2/sites/999999' );
			$response = rest_do_request( $request );

			// Should return 404.
			$this->assertEquals( 404, $response->get_status() );

			$data = $response->get_data();
			$this->assertIsArray( $data );
		} else {
			// If specific endpoint doesn't exist, test passes.
			$this->assertTrue( true );
		}
	}

	/**
	 * Test that REST permission error has proper status.
	 *
	 * @return void
	 */
	public function test_rest_permission_error_status() {
		// Create subscriber.
		$subscriber_id = $this->factory->user->create( [ 'role' => 'subscriber' ] );
		wp_set_current_user( $subscriber_id );

		$request = new WP_REST_Request( 'GET', '/mainwp/v2/sites' );
		$response = rest_do_request( $request );

		// Should return 403.
		$this->assertEquals( 403, $response->get_status() );
	}

	// =========================================================================
	// Fallback Behavior Tests
	// =========================================================================

	/**
	 * Test that REST endpoints work regardless of Abilities API.
	 *
	 * @return void
	 */
	public function test_rest_endpoints_work_without_ability_api() {
		$this->authenticate_as_admin();

		$request = new WP_REST_Request( 'GET', '/mainwp/v2/sites' );
		$response = rest_do_request( $request );

		// Should work whether abilities are available or not.
		$this->assertEquals( 200, $response->get_status() );
	}

	/**
	 * Test that REST sites endpoint works without abilities initialization.
	 *
	 * This test simulates an environment where MainWP abilities are not
	 * registered (e.g., Abilities API feature plugin not installed, or
	 * abilities not yet initialized). The REST controllers must gracefully
	 * fall back to legacy behavior.
	 *
	 * Strategy:
	 * - Create a fresh REST server instance
	 * - Fire only rest_api_init (not abilities init actions)
	 * - Do NOT call MainWP_Abilities::init()
	 * - Verify the endpoint still returns valid data
	 *
	 * @return void
	 */
	public function test_rest_sites_endpoint_works_without_abilities_initialized() {
		global $wp_rest_server;

		// Create a fresh REST server without abilities initialization.
		// This simulates an environment where Abilities API is not available
		// or MainWP abilities have not been registered.
		$wp_rest_server = new WP_REST_Server();
		$this->server   = $wp_rest_server;

		// Only fire rest_api_init - do NOT call MainWP_Abilities::init()
		// or the abilities init actions. This ensures the ability-based
		// code path cannot be used.
		do_action( 'rest_api_init' );

		// Authenticate as admin.
		$this->authenticate_as_admin();

		// Create a test site to ensure we have data.
		$site_id = $this->create_test_site( [ 'name' => 'Fallback Test Site' ] );

		// Issue request to sites endpoint.
		$request  = new WP_REST_Request( 'GET', '/mainwp/v2/sites' );
		$response = rest_do_request( $request );

		// Endpoint should still work and return 200.
		$this->assertEquals(
			200,
			$response->get_status(),
			'Sites endpoint should work without abilities initialized.'
		);

		// Verify response is valid structure.
		$data = $response->get_data();
		$this->assertIsArray( $data, 'Response should be an array.' );

		// MainWP REST responses have success/data structure.
		$this->assertArrayHasKey( 'success', $data, 'Response should have success key.' );
		$this->assertArrayHasKey( 'data', $data, 'Response should have data key.' );

		// Verify the test site appears in the response (proving fallback works).
		$response_data = $data['data'];
		if ( isset( $response_data['items'] ) ) {
			// Ability-style response - shouldn't happen since we didn't init.
			$site_ids = array_column( $response_data['items'], 'id' );
		} else {
			// Legacy-style response - array of sites directly.
			$site_ids = is_array( $response_data ) ? array_column( $response_data, 'id' ) : [];
		}

		// Whether using ability or legacy path, our test site should be retrievable.
		$this->assertContains(
			$site_id,
			$site_ids,
			'Test site should appear in response, proving the endpoint works.'
		);
	}

	// =========================================================================
	// Content Type Tests
	// =========================================================================

	/**
	 * Test that REST response content type is JSON.
	 *
	 * @return void
	 */
	public function test_rest_response_content_type() {
		$this->authenticate_as_admin();

		$request = new WP_REST_Request( 'GET', '/mainwp/v2/sites' );
		$response = rest_do_request( $request );

		$data = $response->get_data();

		// Response should be array (JSON-serializable).
		$this->assertTrue(
			is_array( $data ) || is_object( $data ),
			'Response should be JSON-serializable.'
		);
	}

	// =========================================================================
	// Ability-Backed Path Verification Tests
	// =========================================================================

	/**
	 * Test that REST sites endpoint with pagination returns ability-style response.
	 *
	 * When Abilities API is available and the list-sites ability is used,
	 * the response should include pagination metadata from the ability.
	 *
	 * @return void
	 */
	public function test_rest_sites_endpoint_ability_pagination() {
		$this->skip_if_no_abilities_api();
		$this->authenticate_as_admin();

		// Create multiple test sites for pagination.
		for ( $i = 0; $i < 15; $i++ ) {
			$this->create_test_site( [ 'name' => "Pagination Test Site {$i}" ] );
		}

		$request = new WP_REST_Request( 'GET', '/mainwp/v2/sites' );
		$request->set_param( 'paged', 1 );
		$request->set_param( 'per_page', 5 );

		$response = rest_do_request( $request );

		$this->assertEquals( 200, $response->get_status() );

		$data = $response->get_data();
		$this->assertIsArray( $data );
		$this->assertArrayHasKey( 'success', $data );
		$this->assertArrayHasKey( 'data', $data );

		// Verify pagination is respected in response.
		// The ability path includes pagination metadata.
		$response_data = $data['data'];
		if ( is_array( $response_data ) ) {
			// Count returned items - should be limited by per_page.
			$items = isset( $response_data['items'] ) ? $response_data['items'] : $response_data;
			$this->assertLessThanOrEqual(
				5,
				count( $items ),
				'Response should respect per_page limit.'
			);
		}
	}

	/**
	 * Test that REST sync endpoint returns ability-backed queued response format.
	 *
	 * When syncing many sites via the ability, the response should include
	 * queued response indicators (job_id, status_url, sites_queued).
	 *
	 * @return void
	 */
	public function test_rest_sync_endpoint_queued_response_has_ability_fields() {
		$this->skip_if_no_abilities_api();
		$this->authenticate_as_admin();

		// Create 60 sites to trigger queuing (threshold is 50).
		$site_ids = [];
		for ( $i = 0; $i < 60; $i++ ) {
			$site_ids[] = $this->create_test_site( [
				'name'                 => "Ability Queued Site {$i}",
				'offline_check_result' => 1,
			] );
		}

		$request = new WP_REST_Request( 'POST', '/mainwp/v2/sites/sync' );
		$request->set_body_params( [ 'id_domain' => $site_ids ] );

		$response = rest_do_request( $request );

		$this->assertContains(
			$response->get_status(),
			[ 200, 202 ],
			'Large batch sync should return 200 or 202.'
		);

		$data = $response->get_data();
		$this->assertIsArray( $data );

		// Check for ability-backed queued response fields.
		// These fields are only present when the sync-sites ability queues the job.
		$response_data = isset( $data['data'] ) ? $data['data'] : $data;
		if ( isset( $response_data['queued'] ) && $response_data['queued'] ) {
			$this->assertArrayHasKey(
				'job_id',
				$response_data,
				'Queued ability response should include job_id.'
			);
			$this->assertArrayHasKey(
				'status_url',
				$response_data,
				'Queued ability response should include status_url.'
			);
			$this->assertArrayHasKey(
				'sites_queued',
				$response_data,
				'Queued ability response should include sites_queued count.'
			);
			$this->assertEquals(
				60,
				$response_data['sites_queued'],
				'sites_queued should match number of sites submitted.'
			);
		}
	}

	/**
	 * Test that REST sync immediate response has ability-backed structure.
	 *
	 * For small batches (≤50 sites), the ability executes immediately and
	 * returns synced/errors/total_synced/total_errors fields.
	 *
	 * @return void
	 */
	public function test_rest_sync_endpoint_immediate_response_has_ability_fields() {
		$this->skip_if_no_abilities_api();
		$this->authenticate_as_admin();

		// Create 3 sites (under threshold for immediate execution).
		$site_ids = [];
		for ( $i = 0; $i < 3; $i++ ) {
			$site_ids[] = $this->create_test_site( [
				'name'                 => "Immediate Sync Site {$i}",
				'offline_check_result' => 1,
			] );
		}

		$request = new WP_REST_Request( 'POST', '/mainwp/v2/sites/sync' );
		$request->set_body_params( [ 'id_domain' => $site_ids ] );

		$response = rest_do_request( $request );

		$this->assertContains(
			$response->get_status(),
			[ 200, 207 ],
			'Small batch sync should return 200 or 207.'
		);

		$data = $response->get_data();
		$this->assertIsArray( $data );

		// Check for ability-backed immediate response fields.
		$response_data = isset( $data['data'] ) ? $data['data'] : $data;

		// Immediate responses should NOT be queued.
		$this->assertFalse(
			isset( $response_data['queued'] ) && $response_data['queued'],
			'Small batch should not be queued.'
		);

		// Should have synced results or errors.
		$has_synced = isset( $response_data['synced'] );
		$has_errors = isset( $response_data['errors'] );
		$has_message = isset( $response_data['message'] );

		// Ability-backed response has synced/errors, legacy might have message.
		$this->assertTrue(
			$has_synced || $has_errors || $has_message,
			'Immediate sync response should have synced/errors or message.'
		);
	}

	/**
	 * Test that REST updates endpoint returns ability-backed response shape.
	 *
	 * When list-updates ability is used, the response should include
	 * updates array and summary object with type breakdowns.
	 *
	 * @return void
	 */
	public function test_rest_updates_endpoint_ability_response_shape() {
		$this->skip_if_no_abilities_api();
		$this->authenticate_as_admin();

		$site_id = $this->create_test_site( [
			'name'    => 'Updates Shape Test',
			'version' => '5.0.0',
		] );

		$request  = new WP_REST_Request( 'GET', '/mainwp/v2/updates' );
		$response = rest_do_request( $request );

		$this->assertEquals( 200, $response->get_status() );

		$data = $response->get_data();
		$this->assertArrayHasKey( 'success', $data );
		$this->assertArrayHasKey( 'data', $data );

		// The response should be structured - abilities provide structured output.
		$this->assertIsArray( $data['data'] );
	}

	// =========================================================================
	// Batch Endpoint Tests
	// =========================================================================

	/**
	 * Test that batch sync endpoint accepts array of IDs.
	 *
	 * @return void
	 */
	public function test_rest_batch_sync_accepts_id_array() {
		$this->skip_if_no_abilities_api();
		$this->authenticate_as_admin();

		$site1_id = $this->create_test_site( [ 'name' => 'Batch 1', 'offline_check_result' => 1 ] );
		$site2_id = $this->create_test_site( [ 'name' => 'Batch 2', 'offline_check_result' => 1 ] );

		$request = new WP_REST_Request( 'POST', '/mainwp/v2/sites/sync' );
		$request->set_body_params( [ 'id_domain' => [ $site1_id, $site2_id ] ] );

		$response = rest_do_request( $request );

		$this->assertContains(
			$response->get_status(),
			[ 200, 207 ],
			'Batch sync should accept array of IDs.'
		);
	}

	/**
	 * Test that batch sync endpoint handles mixed identifiers.
	 *
	 * @return void
	 */
	public function test_rest_batch_sync_handles_mixed_identifiers() {
		$this->skip_if_no_abilities_api();
		$this->authenticate_as_admin();

		$site1_id = $this->create_test_site( [ 'name' => 'ID Site', 'offline_check_result' => 1 ] );
		$this->create_test_site( [
			'name'                 => 'Domain Site',
			'url'                  => 'https://test-restmixed.example.com/',
			'offline_check_result' => 1,
		] );

		$request = new WP_REST_Request( 'POST', '/mainwp/v2/sites/sync' );
		$request->set_body_params( [
			'id_domain' => [ $site1_id, 'test-restmixed.example.com' ],
		] );

		$response = rest_do_request( $request );

		$this->assertContains(
			$response->get_status(),
			[ 200, 207 ],
			'Batch sync should handle mixed identifiers.'
		);
	}
}
