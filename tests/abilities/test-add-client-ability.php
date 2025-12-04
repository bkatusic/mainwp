<?php
/**
 * MainWP Add Client Ability Tests
 *
 * Tests for the mainwp/add-client-v1 ability.
 *
 * @package MainWP\Dashboard\Tests
 */

namespace MainWP\Dashboard\Tests;

/**
 * Class Test_Add_Client_Ability
 *
 * Tests for the mainwp/add-client-v1 ability.
 */
class Test_Add_Client_Ability extends MainWP_Abilities_Test_Case {

	/**
	 * Test that the ability is registered.
	 *
	 * @return void
	 */
	public function test_ability_is_registered() {
		$this->skip_if_no_abilities_api();

		$ability = wp_get_ability( 'mainwp/add-client-v1' );
		$this->assertNotNull( $ability, 'Ability mainwp/add-client-v1 should be registered.' );
	}

	/**
	 * Test that add-client returns expected structure.
	 *
	 * @return void
	 */
	public function test_add_client_returns_expected_structure() {
		$this->skip_if_no_abilities_api();
		$this->set_current_user_as_admin();

		$result = $this->execute_ability( 'mainwp/add-client-v1', [
			'name'         => 'New Client',
			'client_email' => 'test-newclient@example.com',
		] );

		$this->assertNotWPError( $result );
		$this->assertIsArray( $result );
		$this->assertArrayHasKey( 'id', $result );
		$this->assertArrayHasKey( 'name', $result );
		$this->assertGreaterThan( 0, $result['id'] );
		$this->assertEquals( 'New Client', $result['name'] );
	}

	/**
	 * Test that add-client requires authentication.
	 *
	 * @return void
	 */
	public function test_add_client_requires_authentication() {
		$this->skip_if_no_abilities_api();
		wp_set_current_user( 0 );

		$result = $this->execute_ability( 'mainwp/add-client-v1', [
			'name' => 'Test Client',
		] );

		$this->assertWPError( $result );
		$this->assertEquals( 'ability_invalid_permissions', $result->get_error_code() );
	}

	/**
	 * Test that add-client requires manage_options capability.
	 *
	 * @return void
	 */
	public function test_add_client_requires_manage_options() {
		$this->skip_if_no_abilities_api();
		$this->set_current_user_as_subscriber();

		$result = $this->execute_ability( 'mainwp/add-client-v1', [
			'name' => 'Test Client',
		] );

		$this->assertWPError( $result );
		$this->assertEquals( 'ability_invalid_permissions', $result->get_error_code() );
	}

	/**
	 * Test that add-client validates input.
	 *
	 * @return void
	 */
	public function test_add_client_validates_input() {
		$this->skip_if_no_abilities_api();
		$this->set_current_user_as_admin();

		$result = $this->execute_ability( 'mainwp/add-client-v1', [] );

		$this->assertWPError( $result );
		$this->assertEquals( 'ability_invalid_input', $result->get_error_code() );
	}

	/**
	 * Test that add-client works with minimal data.
	 *
	 * @return void
	 */
	public function test_add_client_with_minimal_data() {
		$this->skip_if_no_abilities_api();
		$this->set_current_user_as_admin();

		$result = $this->execute_ability( 'mainwp/add-client-v1', [
			'name' => 'Minimal Client',
		] );

		$this->assertNotWPError( $result );
		$this->assertEquals( 'Minimal Client', $result['name'] );
		$this->assertGreaterThan( 0, $result['id'] );
	}

	/**
	 * Test that add-client works with full data.
	 *
	 * @return void
	 */
	public function test_add_client_with_full_data() {
		$this->skip_if_no_abilities_api();
		$this->set_current_user_as_admin();

		$result = $this->execute_ability( 'mainwp/add-client-v1', [
			'name'              => 'Full Data Client',
			'client_email'      => 'test-fulldata@example.com',
			'client_phone'      => '555-1234',
			'address_1'         => '123 Main St',
			'address_2'         => 'Suite 100',
			'city'              => 'Test City',
			'state'             => 'TS',
			'zip'               => '12345',
			'country'           => 'US',
			'note'              => 'Test note',
			'client_facebook'   => 'facebook.com/test',
			'client_twitter'    => 'twitter.com/test',
			'client_instagram'  => 'instagram.com/test',
			'client_linkedin'   => 'linkedin.com/test',
		] );

		$this->assertNotWPError( $result );
		$this->assertEquals( 'Full Data Client', $result['name'] );
		$this->assertEquals( 'test-fulldata@example.com', $result['email'] );
		$this->assertEquals( '555-1234', $result['phone'] );
		$this->assertEquals( '123 Main St', $result['address_1'] );
		$this->assertEquals( 'Test City', $result['city'] );
	}

	/**
	 * Test that add-client persists to database.
	 *
	 * @return void
	 */
	public function test_add_client_persists_to_database() {
		$this->skip_if_no_abilities_api();
		$this->set_current_user_as_admin();

		$result = $this->execute_ability( 'mainwp/add-client-v1', [
			'name'         => 'Persistent Client',
			'client_email' => 'test-persistent@example.com',
		] );

		$this->assertNotWPError( $result );

		$client = $this->get_test_client( $result['id'] );
		$this->assertNotNull( $client );
		$this->assertEquals( 'Persistent Client', $client->name );
		$this->assertEquals( 'test-persistent@example.com', $client->client_email );
	}
}
