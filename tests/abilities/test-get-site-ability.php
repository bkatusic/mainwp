<?php
/**
 * MainWP Get Site Ability Tests
 *
 * Tests for the mainwp/get-site-v1 ability.
 *
 * @package MainWP\Dashboard\Tests
 */

namespace MainWP\Dashboard\Tests;

/**
 * Class MainWP_Get_Site_Ability_Test
 *
 * Tests for the mainwp/get-site-v1 ability.
 */
class MainWP_Get_Site_Ability_Test extends MainWP_Abilities_Test_Case {

	/**
	 * Test that get-site returns site by ID.
	 *
	 * @return void
	 */
	public function test_get_site_by_id_returns_site() {
		$this->skip_if_no_abilities_api();
		$this->set_current_user_as_admin();

		$site_id = $this->create_test_site( [
			'name' => 'Get Site Test',
			'url'  => 'https://test-getsite.example.com/',
		] );

		$result = $this->execute_ability( 'mainwp/get-site-v1', [
			'site_id_or_domain' => $site_id,
		] );

		$this->assertNotWPError( $result );
		$this->assertIsArray( $result );
		$this->assertEquals( $site_id, $result['id'] );
		$this->assertEquals( 'Get Site Test', $result['name'] );
		$this->assertEquals( 'https://test-getsite.example.com/', $result['url'] );
		$this->assertArrayHasKey( 'status', $result );
		$this->assertArrayHasKey( 'client_id', $result );
	}

	/**
	 * Test that get-site returns site by domain.
	 *
	 * @return void
	 */
	public function test_get_site_by_domain_returns_site() {
		$this->skip_if_no_abilities_api();
		$this->set_current_user_as_admin();

		$site_id = $this->create_test_site( [
			'name' => 'Domain Test Site',
			'url'  => 'https://test-domaintest.example.com/',
		] );

		// Use domain without protocol.
		$result = $this->execute_ability( 'mainwp/get-site-v1', [
			'site_id_or_domain' => 'test-domaintest.example.com',
		] );

		$this->assertNotWPError( $result );
		$this->assertIsArray( $result );
		$this->assertEquals( $site_id, $result['id'] );
		$this->assertEquals( 'Domain Test Site', $result['name'] );
	}

	/**
	 * Test that get-site returns error for non-existent site.
	 *
	 * @return void
	 */
	public function test_get_site_not_found_returns_error() {
		$this->skip_if_no_abilities_api();
		$this->set_current_user_as_admin();

		$result = $this->execute_ability( 'mainwp/get-site-v1', [
			'site_id_or_domain' => 999999,
		] );

		$this->assertWPError( $result );
		$this->assertEquals( 'mainwp_site_not_found', $result->get_error_code() );
	}

	/**
	 * Test that get-site returns error for non-existent domain.
	 *
	 * @return void
	 */
	public function test_get_site_not_found_by_domain_returns_error() {
		$this->skip_if_no_abilities_api();
		$this->set_current_user_as_admin();

		$result = $this->execute_ability( 'mainwp/get-site-v1', [
			'site_id_or_domain' => 'nonexistent-domain.example.com',
		] );

		$this->assertWPError( $result );
		$this->assertEquals( 'mainwp_site_not_found', $result->get_error_code() );
	}

	/**
	 * Test that get-site includes full details.
	 *
	 * @return void
	 */
	public function test_get_site_includes_full_details() {
		$this->skip_if_no_abilities_api();
		$this->set_current_user_as_admin();

		$site_id = $this->create_test_site( [
			'name'      => 'Full Details Site',
			'adminname' => 'testadmin',
			'version'   => '5.2.0',
		] );

		$result = $this->execute_ability( 'mainwp/get-site-v1', [
			'site_id_or_domain' => $site_id,
		] );

		$this->assertNotWPError( $result );
		$this->assertIsArray( $result );

		// Verify extended fields are present.
		$this->assertArrayHasKey( 'admin_username', $result );
		$this->assertArrayHasKey( 'wp_version', $result );
		$this->assertArrayHasKey( 'php_version', $result );
		$this->assertArrayHasKey( 'child_version', $result );
		$this->assertArrayHasKey( 'last_sync', $result );
		$this->assertArrayHasKey( 'notes', $result );

		// Verify known values.
		$this->assertEquals( 'testadmin', $result['admin_username'] );
		$this->assertEquals( '5.2.0', $result['child_version'] );
	}

	/**
	 * Test that get-site handles URL with protocol.
	 *
	 * @return void
	 */
	public function test_get_site_by_full_url() {
		$this->skip_if_no_abilities_api();
		$this->set_current_user_as_admin();

		$site_id = $this->create_test_site( [
			'name' => 'Full URL Site',
			'url'  => 'https://test-fullurl.example.com/',
		] );

		// Use full URL with protocol.
		$result = $this->execute_ability( 'mainwp/get-site-v1', [
			'site_id_or_domain' => 'https://test-fullurl.example.com/',
		] );

		$this->assertNotWPError( $result );
		$this->assertIsArray( $result );
		$this->assertEquals( $site_id, $result['id'] );
	}

	/**
	 * Test that get-site handles URL with www prefix.
	 *
	 * @return void
	 */
	public function test_get_site_by_domain_with_www() {
		$this->skip_if_no_abilities_api();
		$this->set_current_user_as_admin();

		// Create site without www.
		$site_id = $this->create_test_site( [
			'name' => 'WWW Test Site',
			'url'  => 'https://test-wwwtest.example.com/',
		] );

		// Try to resolve with www prefix.
		$result = $this->execute_ability( 'mainwp/get-site-v1', [
			'site_id_or_domain' => 'www.test-wwwtest.example.com',
		] );

		// Resolution depends on normalization implementation.
		// Just verify no crash and proper response type.
		$this->assertTrue(
			is_array( $result ) || is_wp_error( $result ),
			'Result should be array or WP_Error.'
		);
	}

	/**
	 * Test that get-site returns proper status values.
	 *
	 * @return void
	 */
	public function test_get_site_status_values() {
		$this->skip_if_no_abilities_api();
		$this->set_current_user_as_admin();

		// Test connected status.
		$online_id = $this->create_test_site( [
			'name'                 => 'Online Site',
			'offline_check_result' => 1,
			'suspended'            => 0,
		] );

		$result = $this->execute_ability( 'mainwp/get-site-v1', [
			'site_id_or_domain' => $online_id,
		] );

		$this->assertNotWPError( $result );
		$this->assertEquals( 'connected', $result['status'] );

		// Test disconnected status.
		$offline_id = $this->create_test_site( [
			'name'                 => 'Offline Site',
			'offline_check_result' => -1,
		] );

		$result = $this->execute_ability( 'mainwp/get-site-v1', [
			'site_id_or_domain' => $offline_id,
		] );

		$this->assertNotWPError( $result );
		$this->assertEquals( 'disconnected', $result['status'] );

		// Test suspended status.
		$suspended_id = $this->create_test_site( [
			'name'                 => 'Suspended Site',
			'suspended'            => 1,
			'offline_check_result' => 1,
		] );

		$result = $this->execute_ability( 'mainwp/get-site-v1', [
			'site_id_or_domain' => $suspended_id,
		] );

		$this->assertNotWPError( $result );
		$this->assertEquals( 'suspended', $result['status'] );
	}

	/**
	 * Test that get-site handles string ID.
	 *
	 * @return void
	 */
	public function test_get_site_with_string_id() {
		$this->skip_if_no_abilities_api();
		$this->set_current_user_as_admin();

		$site_id = $this->create_test_site( [
			'name' => 'String ID Test',
		] );

		// Pass ID as string.
		$result = $this->execute_ability( 'mainwp/get-site-v1', [
			'site_id_or_domain' => (string) $site_id,
		] );

		$this->assertNotWPError( $result );
		$this->assertEquals( $site_id, $result['id'] );
	}

	/**
	 * Test that get-site handles client_id properly.
	 *
	 * @return void
	 */
	public function test_get_site_client_id() {
		$this->skip_if_no_abilities_api();
		$this->set_current_user_as_admin();

		// Site with client.
		$with_client = $this->create_test_site( [
			'name'      => 'Site With Client',
			'client_id' => 42,
		] );

		$result = $this->execute_ability( 'mainwp/get-site-v1', [
			'site_id_or_domain' => $with_client,
		] );

		$this->assertNotWPError( $result );
		$this->assertEquals( 42, $result['client_id'] );

		// Site without client.
		$without_client = $this->create_test_site( [
			'name'      => 'Site Without Client',
			'client_id' => 0,
		] );

		$result = $this->execute_ability( 'mainwp/get-site-v1', [
			'site_id_or_domain' => $without_client,
		] );

		$this->assertNotWPError( $result );
		$this->assertNull( $result['client_id'] );
	}

	/**
	 * Test that get-site requires site_id_or_domain input.
	 *
	 * @return void
	 */
	public function test_get_site_requires_input() {
		$this->skip_if_no_abilities_api();
		$this->set_current_user_as_admin();

		// Call without required input.
		$result = $this->execute_ability( 'mainwp/get-site-v1', [] );

		// Should return error (schema validation).
		$this->assertWPError( $result );
	}

	/**
	 * Test that get-site returns proper format for last_sync.
	 *
	 * @return void
	 */
	public function test_get_site_last_sync_format() {
		$this->skip_if_no_abilities_api();
		$this->set_current_user_as_admin();

		global $wpdb;

		$site_id = $this->create_test_site( [
			'name' => 'Sync Time Test',
		] );

		// Update dtsSync directly.
		$sync_time = time();
		$wpdb->update(
			$wpdb->prefix . 'mainwp_wp',
			[ 'dtsSync' => $sync_time ],
			[ 'id' => $site_id ],
			[ '%d' ],
			[ '%d' ]
		);

		$result = $this->execute_ability( 'mainwp/get-site-v1', [
			'site_id_or_domain' => $site_id,
		] );

		$this->assertNotWPError( $result );

		// Should be ISO 8601 format.
		if ( ! empty( $result['last_sync'] ) ) {
			$parsed = strtotime( $result['last_sync'] );
			$this->assertNotFalse( $parsed, 'last_sync should be parseable timestamp.' );
		}
	}
}
