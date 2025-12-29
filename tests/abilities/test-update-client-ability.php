<?php
/**
 * MainWP Update Client Ability Tests
 *
 * Tests for the mainwp/update-client-v1 ability.
 *
 * @package MainWP\Dashboard\Tests
 */

namespace MainWP\Dashboard\Tests;

/**
 * Class Test_Update_Client_Ability
 *
 * Tests for the mainwp/update-client-v1 ability.
 */
class Test_Update_Client_Ability extends MainWP_Abilities_Test_Case {

	/**
	 * Test that the ability is registered.
	 *
	 * @return void
	 */
	public function test_ability_is_registered() {
		$this->skip_if_no_abilities_api();

		$ability = wp_get_ability( 'mainwp/update-client-v1' );
		$this->assertNotNull( $ability, 'Ability mainwp/update-client-v1 should be registered.' );
	}

	/**
	 * Test that update-client returns expected structure.
	 *
	 * @return void
	 */
	public function test_update_client_returns_expected_structure() {
		$this->skip_if_no_abilities_api();
		$this->set_current_user_as_admin();

		$client_id = $this->create_test_client( [
			'name' => 'Original Name',
		] );

		$result = $this->execute_ability( 'mainwp/update-client-v1', [
			'client_id_or_email' => $client_id,
			'name'               => 'Updated Name',
		] );

		$this->assertNotWPError( $result );
		$this->assertIsArray( $result );
		$this->assertArrayHasKey( 'id', $result );
		$this->assertArrayHasKey( 'name', $result );
		$this->assertEquals( $client_id, $result['id'] );
		$this->assertEquals( 'Updated Name', $result['name'] );
	}

	/**
	 * Test that update-client requires authentication.
	 *
	 * @return void
	 */
	public function test_update_client_requires_authentication() {
		$this->skip_if_no_abilities_api();
		wp_set_current_user( 0 );

		// Expect the "doing it wrong" notice from WP_Ability::execute.
		$this->setExpectedIncorrectUsage( 'WP_Ability::execute' );

		$result = $this->execute_ability( 'mainwp/update-client-v1', [
			'client_id_or_email' => 1,
			'name'               => 'Test',
		] );

		$this->assertWPError( $result );
		$this->assertEquals( 'ability_invalid_permissions', $result->get_error_code() );
	}

	/**
	 * Test that update-client requires manage_options capability.
	 *
	 * @return void
	 */
	public function test_update_client_requires_manage_options() {
		$this->skip_if_no_abilities_api();
		$this->set_current_user_as_subscriber();

		// Expect the "doing it wrong" notice from WP_Ability::execute.
		$this->setExpectedIncorrectUsage( 'WP_Ability::execute' );

		$result = $this->execute_ability( 'mainwp/update-client-v1', [
			'client_id_or_email' => 1,
			'name'               => 'Test',
		] );

		$this->assertWPError( $result );
		$this->assertEquals( 'ability_invalid_permissions', $result->get_error_code() );
	}

	/**
	 * Test that update-client validates input.
	 *
	 * @return void
	 */
	public function test_update_client_validates_input() {
		$this->skip_if_no_abilities_api();
		$this->set_current_user_as_admin();

		$result = $this->execute_ability( 'mainwp/update-client-v1', [] );

		$this->assertWPError( $result );
		$this->assertEquals( 'ability_invalid_input', $result->get_error_code() );
	}

	/**
	 * Test that update-client works with numeric ID.
	 *
	 * @return void
	 */
	public function test_update_client_by_id() {
		$this->skip_if_no_abilities_api();
		$this->set_current_user_as_admin();

		$client_id = $this->create_test_client( [
			'name' => 'Update By ID',
		] );

		$result = $this->execute_ability( 'mainwp/update-client-v1', [
			'client_id_or_email' => $client_id,
			'name'               => 'Updated By ID',
		] );

		$this->assertNotWPError( $result );
		$this->assertEquals( 'Updated By ID', $result['name'] );
	}

	/**
	 * Test that update-client works with email.
	 *
	 * @return void
	 */
	public function test_update_client_by_email() {
		$this->skip_if_no_abilities_api();
		$this->set_current_user_as_admin();

		$this->create_test_client( [
			'name'         => 'Update By Email',
			'client_email' => 'test-updatebyemail@example.com',
		] );

		$result = $this->execute_ability( 'mainwp/update-client-v1', [
			'client_id_or_email' => 'test-updatebyemail@example.com',
			'name'               => 'Updated By Email',
		] );

		$this->assertNotWPError( $result );
		$this->assertEquals( 'Updated By Email', $result['name'] );
	}

	/**
	 * Test that update-client handles partial update.
	 *
	 * @return void
	 */
	public function test_update_client_partial_update() {
		$this->skip_if_no_abilities_api();
		$this->set_current_user_as_admin();

		$client_id = $this->create_test_client( [
			'name'         => 'Partial Update',
			'client_email' => 'test-partial@example.com',
			'client_phone' => '555-0000',
		] );

		$result = $this->execute_ability( 'mainwp/update-client-v1', [
			'client_id_or_email' => $client_id,
			'name'               => 'Partially Updated',
		] );

		$this->assertNotWPError( $result );
		$this->assertEquals( 'Partially Updated', $result['name'] );
		$this->assertEquals( 'test-partial@example.com', $result['email'] );
		$this->assertEquals( '555-0000', $result['phone'] );
	}

	/**
	 * Test that update-client returns not found error.
	 *
	 * @return void
	 */
	public function test_update_client_returns_not_found_error() {
		$this->skip_if_no_abilities_api();
		$this->set_current_user_as_admin();

		$result = $this->execute_ability( 'mainwp/update-client-v1', [
			'client_id_or_email' => 999999,
			'name'               => 'Test',
		] );

		$this->assertWPError( $result );
		$this->assertEquals( 'mainwp_client_not_found', $result->get_error_code() );
	}
}
