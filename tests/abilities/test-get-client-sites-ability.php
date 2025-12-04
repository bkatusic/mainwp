<?php
/**
 * MainWP Get Client Sites Ability Tests
 *
 * Tests for the mainwp/get-client-sites-v1 ability.
 *
 * @package MainWP\Dashboard\Tests
 */

namespace MainWP\Dashboard\Tests;

/**
 * Class Test_Get_Client_Sites_Ability
 *
 * Tests for the mainwp/get-client-sites-v1 ability.
 */
class Test_Get_Client_Sites_Ability extends MainWP_Abilities_Test_Case {

	/**
	 * Test that the ability is registered.
	 *
	 * @return void
	 */
	public function test_ability_is_registered() {
		$this->skip_if_no_abilities_api();

		$ability = wp_get_ability( 'mainwp/get-client-sites-v1' );
		$this->assertNotNull( $ability, 'Ability mainwp/get-client-sites-v1 should be registered.' );
	}

	/**
	 * Test that get-client-sites returns expected structure.
	 *
	 * @return void
	 */
	public function test_get_client_sites_returns_expected_structure() {
		$this->skip_if_no_abilities_api();
		$this->set_current_user_as_admin();

		$client_id = $this->create_test_client( [
			'name' => 'Test Client',
		] );

		$result = $this->execute_ability( 'mainwp/get-client-sites-v1', [
			'client_id_or_email' => $client_id,
		] );

		$this->assertNotWPError( $result );
		$this->assertIsArray( $result );
		$this->assertArrayHasKey( 'items', $result );
		$this->assertArrayHasKey( 'total', $result );
		$this->assertIsArray( $result['items'] );
		$this->assertIsInt( $result['total'] );
	}

	/**
	 * Test that get-client-sites requires authentication.
	 *
	 * @return void
	 */
	public function test_get_client_sites_requires_authentication() {
		$this->skip_if_no_abilities_api();
		wp_set_current_user( 0 );

		$result = $this->execute_ability( 'mainwp/get-client-sites-v1', [
			'client_id_or_email' => 1,
		] );

		$this->assertWPError( $result );
		$this->assertEquals( 'ability_invalid_permissions', $result->get_error_code() );
	}

	/**
	 * Test that get-client-sites requires manage_options capability.
	 *
	 * @return void
	 */
	public function test_get_client_sites_requires_manage_options() {
		$this->skip_if_no_abilities_api();
		$this->set_current_user_as_subscriber();

		$result = $this->execute_ability( 'mainwp/get-client-sites-v1', [
			'client_id_or_email' => 1,
		] );

		$this->assertWPError( $result );
		$this->assertEquals( 'ability_invalid_permissions', $result->get_error_code() );
	}

	/**
	 * Test that get-client-sites validates input.
	 *
	 * @return void
	 */
	public function test_get_client_sites_validates_input() {
		$this->skip_if_no_abilities_api();
		$this->set_current_user_as_admin();

		$result = $this->execute_ability( 'mainwp/get-client-sites-v1', [] );

		$this->assertWPError( $result );
		$this->assertEquals( 'ability_invalid_input', $result->get_error_code() );
	}

	/**
	 * Test that get-client-sites returns associated sites.
	 *
	 * @return void
	 */
	public function test_get_client_sites_returns_associated_sites() {
		$this->skip_if_no_abilities_api();
		$this->set_current_user_as_admin();

		$client_id = $this->create_test_client( [
			'name' => 'Client With Sites',
		] );

		$site1_id = $this->create_test_site( [
			'name'      => 'Site 1',
			'url'       => 'https://test-site1.example.com/',
			'client_id' => $client_id,
		] );

		$site2_id = $this->create_test_site( [
			'name'      => 'Site 2',
			'url'       => 'https://test-site2.example.com/',
			'client_id' => $client_id,
		] );

		$site3_id = $this->create_test_site( [
			'name'      => 'Site 3',
			'url'       => 'https://test-site3.example.com/',
			'client_id' => $client_id,
		] );

		$result = $this->execute_ability( 'mainwp/get-client-sites-v1', [
			'client_id_or_email' => $client_id,
		] );

		$this->assertNotWPError( $result );
		$this->assertEquals( 3, $result['total'] );
		$this->assertCount( 3, $result['items'] );

		$site_ids = array_map( function( $site ) {
			return $site['id'];
		}, $result['items'] );

		$this->assertContains( $site1_id, $site_ids );
		$this->assertContains( $site2_id, $site_ids );
		$this->assertContains( $site3_id, $site_ids );
	}

	/**
	 * Test that get-client-sites returns empty for no sites.
	 *
	 * @return void
	 */
	public function test_get_client_sites_returns_empty_for_no_sites() {
		$this->skip_if_no_abilities_api();
		$this->set_current_user_as_admin();

		$client_id = $this->create_test_client( [
			'name' => 'Client Without Sites',
		] );

		$result = $this->execute_ability( 'mainwp/get-client-sites-v1', [
			'client_id_or_email' => $client_id,
		] );

		$this->assertNotWPError( $result );
		$this->assertEquals( 0, $result['total'] );
		$this->assertEmpty( $result['items'] );
	}

	/**
	 * Test that get-client-sites returns not found error.
	 *
	 * @return void
	 */
	public function test_get_client_sites_returns_not_found_error() {
		$this->skip_if_no_abilities_api();
		$this->set_current_user_as_admin();

		$result = $this->execute_ability( 'mainwp/get-client-sites-v1', [
			'client_id_or_email' => 999999,
		] );

		$this->assertWPError( $result );
		$this->assertEquals( 'mainwp_client_not_found', $result->get_error_code() );
	}
}
