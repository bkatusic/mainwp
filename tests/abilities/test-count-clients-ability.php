<?php
/**
 * MainWP Count Clients Ability Tests
 *
 * Tests for the mainwp/count-clients-v1 ability.
 *
 * @package MainWP\Dashboard\Tests
 */

namespace MainWP\Dashboard\Tests;

/**
 * Class Test_Count_Clients_Ability
 *
 * Tests for the mainwp/count-clients-v1 ability.
 */
class Test_Count_Clients_Ability extends MainWP_Abilities_Test_Case {

	/**
	 * Test that the ability is registered.
	 *
	 * @return void
	 */
	public function test_ability_is_registered() {
		$this->skip_if_no_abilities_api();

		$ability = wp_get_ability( 'mainwp/count-clients-v1' );
		$this->assertNotNull( $ability, 'Ability mainwp/count-clients-v1 should be registered.' );
	}

	/**
	 * Test that count-clients returns expected structure.
	 *
	 * @return void
	 */
	public function test_count_clients_returns_expected_structure() {
		$this->skip_if_no_abilities_api();
		$this->set_current_user_as_admin();

		$result = $this->execute_ability( 'mainwp/count-clients-v1', [] );

		$this->assertNotWPError( $result );
		$this->assertIsArray( $result );
		$this->assertArrayHasKey( 'total', $result );
		$this->assertIsInt( $result['total'] );
	}

	/**
	 * Test that count-clients requires authentication.
	 *
	 * @return void
	 */
	public function test_count_clients_requires_authentication() {
		$this->skip_if_no_abilities_api();
		wp_set_current_user( 0 );

		// Expect the "doing it wrong" notice from WP_Ability::execute.
		$this->setExpectedIncorrectUsage( 'WP_Ability::execute' );

		$result = $this->execute_ability( 'mainwp/count-clients-v1', [] );

		$this->assertWPError( $result );
		$this->assertEquals( 'ability_invalid_permissions', $result->get_error_code() );
	}

	/**
	 * Test that count-clients requires manage_options capability.
	 *
	 * @return void
	 */
	public function test_count_clients_requires_manage_options() {
		$this->skip_if_no_abilities_api();
		$this->set_current_user_as_subscriber();

		// Expect the "doing it wrong" notice from WP_Ability::execute.
		$this->setExpectedIncorrectUsage( 'WP_Ability::execute' );

		$result = $this->execute_ability( 'mainwp/count-clients-v1', [] );

		$this->assertWPError( $result );
		$this->assertEquals( 'ability_invalid_permissions', $result->get_error_code() );
	}

	/**
	 * Test that count-clients counts multiple clients correctly.
	 *
	 * @return void
	 */
	public function test_count_clients_with_multiple_clients() {
		$this->skip_if_no_abilities_api();
		$this->set_current_user_as_admin();

		global $wpdb;
		$wpdb->query( "DELETE FROM {$wpdb->prefix}mainwp_wp_clients" );

		for ( $i = 1; $i <= 5; $i++ ) {
			$this->create_test_client( [
				'name' => 'Test Client ' . $i,
			] );
		}

		$result = $this->execute_ability( 'mainwp/count-clients-v1', [] );

		$this->assertNotWPError( $result );
		$this->assertEquals( 5, $result['total'] );
	}

	/**
	 * Test that count-clients returns zero for empty database.
	 *
	 * @return void
	 */
	public function test_count_clients_with_empty_database() {
		$this->skip_if_no_abilities_api();
		$this->set_current_user_as_admin();

		global $wpdb;
		$wpdb->query( "DELETE FROM {$wpdb->prefix}mainwp_wp_clients" );

		$result = $this->execute_ability( 'mainwp/count-clients-v1', [] );

		$this->assertNotWPError( $result );
		$this->assertEquals( 0, $result['total'] );
	}
}
