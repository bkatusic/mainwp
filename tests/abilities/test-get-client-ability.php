<?php
/**
 * MainWP Get Client Ability Tests
 *
 * Tests for the mainwp/get-client-v1 ability.
 *
 * @package MainWP\Dashboard\Tests
 */

namespace MainWP\Dashboard\Tests;

/**
 * Class Test_Get_Client_Ability
 *
 * Tests for the mainwp/get-client-v1 ability.
 */
class Test_Get_Client_Ability extends MainWP_Abilities_Test_Case {

	/**
	 * Test that the ability is registered.
	 *
	 * @return void
	 */
	public function test_ability_is_registered() {
		$this->skip_if_no_abilities_api();

		$ability = wp_get_ability( 'mainwp/get-client-v1' );
		$this->assertNotNull( $ability, 'Ability mainwp/get-client-v1 should be registered.' );
	}

	/**
	 * Test that get-client returns expected structure.
	 *
	 * @return void
	 */
	public function test_get_client_returns_expected_structure() {
		$this->skip_if_no_abilities_api();
		$this->set_current_user_as_admin();

		$client_id = $this->create_test_client( [
			'name'         => 'Test Client',
			'client_email' => 'test-client@example.com',
		] );

		$result = $this->execute_ability( 'mainwp/get-client-v1', [
			'client_id_or_email' => $client_id,
		] );

		$this->assertNotWPError( $result );
		$this->assertIsArray( $result );
		$this->assertArrayHasKey( 'id', $result );
		$this->assertArrayHasKey( 'name', $result );
		$this->assertArrayHasKey( 'email', $result );
		$this->assertArrayHasKey( 'phone', $result );
		$this->assertArrayHasKey( 'suspended', $result );
		$this->assertArrayHasKey( 'created', $result );
		$this->assertEquals( $client_id, $result['id'] );
		$this->assertEquals( 'Test Client', $result['name'] );
	}

	/**
	 * Test that get-client requires authentication.
	 *
	 * @return void
	 */
	public function test_get_client_requires_authentication() {
		$this->skip_if_no_abilities_api();
		wp_set_current_user( 0 );

		// Expect the "doing it wrong" notice from WP_Ability::execute.
		$this->setExpectedIncorrectUsage( 'WP_Ability::execute' );

		$result = $this->execute_ability( 'mainwp/get-client-v1', [
			'client_id_or_email' => 1,
		] );

		$this->assertWPError( $result );
		$this->assertEquals( 'ability_invalid_permissions', $result->get_error_code() );
	}

	/**
	 * Test that get-client requires manage_options capability.
	 *
	 * @return void
	 */
	public function test_get_client_requires_manage_options() {
		$this->skip_if_no_abilities_api();
		$this->set_current_user_as_subscriber();

		// Expect the "doing it wrong" notice from WP_Ability::execute.
		$this->setExpectedIncorrectUsage( 'WP_Ability::execute' );

		$result = $this->execute_ability( 'mainwp/get-client-v1', [
			'client_id_or_email' => 1,
		] );

		$this->assertWPError( $result );
		$this->assertEquals( 'ability_invalid_permissions', $result->get_error_code() );
	}

	/**
	 * Test that get-client validates input.
	 *
	 * @return void
	 */
	public function test_get_client_validates_input() {
		$this->skip_if_no_abilities_api();
		$this->set_current_user_as_admin();

		$result = $this->execute_ability( 'mainwp/get-client-v1', [] );

		$this->assertWPError( $result );
		$this->assertEquals( 'ability_invalid_input', $result->get_error_code() );
	}

	/**
	 * Test that get-client works with numeric ID.
	 *
	 * @return void
	 */
	public function test_get_client_by_id() {
		$this->skip_if_no_abilities_api();
		$this->set_current_user_as_admin();

		$client_id = $this->create_test_client( [
			'name' => 'Test Client By ID',
		] );

		$result = $this->execute_ability( 'mainwp/get-client-v1', [
			'client_id_or_email' => $client_id,
		] );

		$this->assertNotWPError( $result );
		$this->assertEquals( $client_id, $result['id'] );
		$this->assertEquals( 'Test Client By ID', $result['name'] );
	}

	/**
	 * Test that get-client works with email.
	 *
	 * @return void
	 */
	public function test_get_client_by_email() {
		$this->skip_if_no_abilities_api();
		$this->set_current_user_as_admin();

		$client_id = $this->create_test_client( [
			'name'         => 'Test Client By Email',
			'client_email' => 'test-byemail@example.com',
		] );

		$result = $this->execute_ability( 'mainwp/get-client-v1', [
			'client_id_or_email' => 'test-byemail@example.com',
		] );

		$this->assertNotWPError( $result );
		$this->assertEquals( $client_id, $result['id'] );
		$this->assertEquals( 'test-byemail@example.com', $result['email'] );
	}

	/**
	 * Test that get-client returns not found error.
	 *
	 * @return void
	 */
	public function test_get_client_returns_not_found_error() {
		$this->skip_if_no_abilities_api();
		$this->set_current_user_as_admin();

		$result = $this->execute_ability( 'mainwp/get-client-v1', [
			'client_id_or_email' => 999999,
		] );

		$this->assertWPError( $result );
		$this->assertEquals( 'mainwp_client_not_found', $result->get_error_code() );
	}

	/**
	 * Test that email lookup returns the correct client and fields.
	 *
	 * Note: The database enforces unique emails so we test
	 * that email lookup returns the correct single match.
	 *
	 * @return void
	 */
	public function test_get_client_by_email_returns_full_result() {
		$this->skip_if_no_abilities_api();
		$this->set_current_user_as_admin();

		$email     = 'unique-' . wp_generate_uuid4() . '@example.com';
		$client_id = $this->create_test_client( [
			'name'         => 'Email Test Client',
			'client_email' => $email,
		] );

		$result = $this->execute_ability( 'mainwp/get-client-v1', [
			'client_id_or_email' => $email,
		] );

		$this->assertNotWPError( $result );
		$this->assertEquals( $client_id, $result['id'] );
		$this->assertEquals( $email, $result['email'] );
	}
}
