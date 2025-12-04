<?php
/**
 * MainWP Delete Client Ability Tests
 *
 * Tests for the mainwp/delete-client-v1 ability.
 *
 * @package MainWP\Dashboard\Tests
 */

namespace MainWP\Dashboard\Tests;

/**
 * Class Test_Delete_Client_Ability
 *
 * Tests for the mainwp/delete-client-v1 ability (destructive operation).
 */
class Test_Delete_Client_Ability extends MainWP_Abilities_Test_Case {

	/**
	 * Test that the ability is registered.
	 *
	 * @return void
	 */
	public function test_ability_is_registered() {
		$this->skip_if_no_abilities_api();

		$ability = wp_get_ability( 'mainwp/delete-client-v1' );
		$this->assertNotNull( $ability, 'Ability mainwp/delete-client-v1 should be registered.' );
	}

	/**
	 * Test that delete-client returns expected structure.
	 *
	 * @return void
	 */
	public function test_delete_client_returns_expected_structure() {
		$this->skip_if_no_abilities_api();
		$this->set_current_user_as_admin();

		$client_id = $this->create_test_client( [
			'name' => 'Client To Delete',
		] );

		$result = $this->execute_ability( 'mainwp/delete-client-v1', [
			'client_id_or_email' => $client_id,
			'confirm'            => true,
		] );

		$this->assertNotWPError( $result );
		$this->assertIsArray( $result );
		$this->assertArrayHasKey( 'id', $result );
		$this->assertArrayHasKey( 'name', $result );
		$this->assertEquals( $client_id, $result['id'] );
	}

	/**
	 * Test that delete-client requires authentication.
	 *
	 * @return void
	 */
	public function test_delete_client_requires_authentication() {
		$this->skip_if_no_abilities_api();
		wp_set_current_user( 0 );

		$result = $this->execute_ability( 'mainwp/delete-client-v1', [
			'client_id_or_email' => 1,
			'confirm'            => true,
		] );

		$this->assertWPError( $result );
		$this->assertEquals( 'ability_invalid_permissions', $result->get_error_code() );
	}

	/**
	 * Test that delete-client requires manage_options capability.
	 *
	 * @return void
	 */
	public function test_delete_client_requires_manage_options() {
		$this->skip_if_no_abilities_api();
		$this->set_current_user_as_subscriber();

		$result = $this->execute_ability( 'mainwp/delete-client-v1', [
			'client_id_or_email' => 1,
			'confirm'            => true,
		] );

		$this->assertWPError( $result );
		$this->assertEquals( 'ability_invalid_permissions', $result->get_error_code() );
	}

	/**
	 * Test that delete-client validates input.
	 *
	 * @return void
	 */
	public function test_delete_client_validates_input() {
		$this->skip_if_no_abilities_api();
		$this->set_current_user_as_admin();

		$result = $this->execute_ability( 'mainwp/delete-client-v1', [] );

		$this->assertWPError( $result );
		$this->assertEquals( 'ability_invalid_input', $result->get_error_code() );
	}

	/**
	 * Test that delete-client requires confirmation.
	 *
	 * @return void
	 */
	public function test_delete_client_requires_confirmation() {
		$this->skip_if_no_abilities_api();
		$this->set_current_user_as_admin();

		$client_id = $this->create_test_client( [
			'name' => 'Client Without Confirm',
		] );

		$result = $this->execute_ability( 'mainwp/delete-client-v1', [
			'client_id_or_email' => $client_id,
		] );

		$this->assertWPError( $result );
		$this->assertEquals( 'mainwp_confirmation_required', $result->get_error_code() );

		$client = $this->get_test_client( $client_id );
		$this->assertNotNull( $client, 'Client should still exist without confirmation.' );
	}

	/**
	 * Test that delete-client dry_run returns preview.
	 *
	 * @return void
	 */
	public function test_delete_client_dry_run_returns_preview() {
		$this->skip_if_no_abilities_api();
		$this->set_current_user_as_admin();

		$client_id = $this->create_test_client( [
			'name' => 'Client Dry Run',
		] );

		$result = $this->execute_ability( 'mainwp/delete-client-v1', [
			'client_id_or_email' => $client_id,
			'dry_run'            => true,
		] );

		$this->assertNotWPError( $result );
		$this->assertIsArray( $result );
		$this->assertArrayHasKey( 'dry_run', $result );
		$this->assertArrayHasKey( 'would_affect', $result );
		$this->assertArrayHasKey( 'count', $result );
		$this->assertArrayHasKey( 'warnings', $result );
		$this->assertTrue( $result['dry_run'] );
		$this->assertEquals( 1, $result['count'] );

		$client = $this->get_test_client( $client_id );
		$this->assertNotNull( $client, 'Client should still exist after dry run.' );
	}

	/**
	 * Test that delete-client rejects both dry_run and confirm.
	 *
	 * @return void
	 */
	public function test_delete_client_rejects_dry_run_and_confirm_together() {
		$this->skip_if_no_abilities_api();
		$this->set_current_user_as_admin();

		$client_id = $this->create_test_client( [
			'name' => 'Test Client',
		] );

		$result = $this->execute_ability( 'mainwp/delete-client-v1', [
			'client_id_or_email' => $client_id,
			'dry_run'            => true,
			'confirm'            => true,
		] );

		$this->assertWPError( $result );
		$this->assertEquals( 'mainwp_invalid_input', $result->get_error_code() );
	}

	/**
	 * Test that delete-client removes from database.
	 *
	 * @return void
	 */
	public function test_delete_client_removes_from_database() {
		$this->skip_if_no_abilities_api();
		$this->set_current_user_as_admin();

		$client_id = $this->create_test_client( [
			'name' => 'Client To Remove',
		] );

		$result = $this->execute_ability( 'mainwp/delete-client-v1', [
			'client_id_or_email' => $client_id,
			'confirm'            => true,
		] );

		$this->assertNotWPError( $result );

		$client = $this->get_test_client( $client_id );
		$this->assertNull( $client, 'Client should be removed from database.' );
	}

	/**
	 * Test that delete-client dry_run includes sites count.
	 *
	 * @return void
	 */
	public function test_delete_client_dry_run_includes_sites_count() {
		$this->skip_if_no_abilities_api();
		$this->set_current_user_as_admin();

		$client_id = $this->create_test_client( [
			'name' => 'Client With Sites',
		] );

		$site_id = $this->create_test_site( [
			'name'      => 'Test Site',
			'url'       => 'https://test-withclient.example.com/',
			'client_id' => $client_id,
		] );

		$result = $this->execute_ability( 'mainwp/delete-client-v1', [
			'client_id_or_email' => $client_id,
			'dry_run'            => true,
		] );

		$this->assertNotWPError( $result );
		$this->assertArrayHasKey( 'would_affect', $result );
		$this->assertArrayHasKey( 'associated_sites_count', $result['would_affect'] );
		$this->assertGreaterThanOrEqual( 1, $result['would_affect']['associated_sites_count'] );
		$this->assertNotEmpty( $result['warnings'], 'Should have warnings about associated sites.' );
	}
}
