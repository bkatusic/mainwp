<?php
/**
 * MainWP Get Client Costs Ability Tests
 *
 * Tests for the mainwp/get-client-costs-v1 ability (feature-gated).
 *
 * @package MainWP\Dashboard\Tests
 */

namespace MainWP\Dashboard\Tests;

/**
 * Class Test_Get_Client_Costs_Ability
 *
 * Tests for the mainwp/get-client-costs-v1 ability (requires Cost Tracker module).
 */
class Test_Get_Client_Costs_Ability extends MainWP_Abilities_Test_Case {

	/**
	 * Test that the ability is registered when Cost Tracker is available.
	 *
	 * @return void
	 */
	public function test_ability_is_registered() {
		$this->skip_if_no_abilities_api();

		if ( ! class_exists( 'MainWP\Dashboard\Module\CostTracker\Cost_Tracker' ) ) {
			$this->markTestSkipped( 'Cost Tracker module not available' );
		}

		$ability = wp_get_ability( 'mainwp/get-client-costs-v1' );
		$this->assertNotNull( $ability, 'Ability mainwp/get-client-costs-v1 should be registered when Cost Tracker is available.' );
	}

	/**
	 * Test that get-client-costs returns expected structure.
	 *
	 * @return void
	 */
	public function test_get_client_costs_returns_expected_structure() {
		$this->skip_if_no_abilities_api();

		if ( ! class_exists( 'MainWP\Dashboard\Module\CostTracker\Cost_Tracker' ) ) {
			$this->markTestSkipped( 'Cost Tracker module not available' );
		}

		$this->set_current_user_as_admin();

		$client_id = $this->create_test_client( [
			'name' => 'Test Client',
		] );

		$result = $this->execute_ability( 'mainwp/get-client-costs-v1', [
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
	 * Test that get-client-costs requires authentication.
	 *
	 * @return void
	 */
	public function test_get_client_costs_requires_authentication() {
		$this->skip_if_no_abilities_api();

		if ( ! class_exists( 'MainWP\Dashboard\Module\CostTracker\Cost_Tracker' ) ) {
			$this->markTestSkipped( 'Cost Tracker module not available' );
		}

		wp_set_current_user( 0 );

		$result = $this->execute_ability( 'mainwp/get-client-costs-v1', [
			'client_id_or_email' => 1,
		] );

		$this->assertWPError( $result );
		$this->assertEquals( 'ability_invalid_permissions', $result->get_error_code() );
	}

	/**
	 * Test that get-client-costs requires manage_options capability.
	 *
	 * @return void
	 */
	public function test_get_client_costs_requires_manage_options() {
		$this->skip_if_no_abilities_api();

		if ( ! class_exists( 'MainWP\Dashboard\Module\CostTracker\Cost_Tracker' ) ) {
			$this->markTestSkipped( 'Cost Tracker module not available' );
		}

		$this->set_current_user_as_subscriber();

		$result = $this->execute_ability( 'mainwp/get-client-costs-v1', [
			'client_id_or_email' => 1,
		] );

		$this->assertWPError( $result );
		$this->assertEquals( 'ability_invalid_permissions', $result->get_error_code() );
	}

	/**
	 * Test that get-client-costs validates input.
	 *
	 * @return void
	 */
	public function test_get_client_costs_validates_input() {
		$this->skip_if_no_abilities_api();

		if ( ! class_exists( 'MainWP\Dashboard\Module\CostTracker\Cost_Tracker' ) ) {
			$this->markTestSkipped( 'Cost Tracker module not available' );
		}

		$this->set_current_user_as_admin();

		$result = $this->execute_ability( 'mainwp/get-client-costs-v1', [] );

		$this->assertWPError( $result );
		$this->assertEquals( 'ability_invalid_input', $result->get_error_code() );
	}

	/**
	 * Test that get-client-costs returns empty for no costs.
	 *
	 * @return void
	 */
	public function test_get_client_costs_returns_empty_for_no_costs() {
		$this->skip_if_no_abilities_api();

		if ( ! class_exists( 'MainWP\Dashboard\Module\CostTracker\Cost_Tracker' ) ) {
			$this->markTestSkipped( 'Cost Tracker module not available' );
		}

		$this->set_current_user_as_admin();

		$client_id = $this->create_test_client( [
			'name' => 'Client Without Costs',
		] );

		$result = $this->execute_ability( 'mainwp/get-client-costs-v1', [
			'client_id_or_email' => $client_id,
		] );

		$this->assertNotWPError( $result );
		$this->assertEquals( 0, $result['total'] );
		$this->assertEmpty( $result['items'] );
	}

	/**
	 * Test that ability is not registered without Cost Tracker module.
	 *
	 * @return void
	 */
	public function test_get_client_costs_not_registered_without_module() {
		$this->skip_if_no_abilities_api();

		if ( class_exists( 'MainWP\Dashboard\Module\CostTracker\Cost_Tracker' ) ) {
			$this->markTestSkipped( 'Cost Tracker module is available, cannot test absence' );
		}

		$ability = wp_get_ability( 'mainwp/get-client-costs-v1' );
		$this->assertNull( $ability, 'Ability mainwp/get-client-costs-v1 should not be registered without Cost Tracker.' );
	}
}
