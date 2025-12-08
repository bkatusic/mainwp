<?php
/**
 * MainWP Count Client Sites Ability Tests
 *
 * Tests for the mainwp/count-client-sites-v1 ability.
 *
 * @package MainWP\Dashboard\Tests
 */

namespace MainWP\Dashboard\Tests;

/**
 * Class Test_Count_Client_Sites_Ability
 *
 * Tests for the mainwp/count-client-sites-v1 ability.
 */
class Test_Count_Client_Sites_Ability extends MainWP_Abilities_Test_Case {

	/**
	 * Test that the ability is registered.
	 *
	 * @return void
	 */
	public function test_ability_is_registered() {
		$this->skip_if_no_abilities_api();

		$ability = wp_get_ability( 'mainwp/count-client-sites-v1' );
		$this->assertNotNull( $ability, 'Ability mainwp/count-client-sites-v1 should be registered.' );
	}

	/**
	 * Test that count-client-sites returns expected structure.
	 *
	 * @return void
	 */
	public function test_count_client_sites_returns_expected_structure() {
		$this->skip_if_no_abilities_api();
		$this->set_current_user_as_admin();

		$client_id = $this->create_test_client( [
			'name' => 'Test Client',
		] );

		$result = $this->execute_ability( 'mainwp/count-client-sites-v1', [
			'client_id_or_email' => $client_id,
		] );

		$this->assertNotWPError( $result );
		$this->assertIsArray( $result );
		$this->assertArrayHasKey( 'total', $result );
		$this->assertIsInt( $result['total'] );
	}

	/**
	 * Test that count-client-sites requires authentication.
	 *
	 * @return void
	 */
	public function test_count_client_sites_requires_authentication() {
		$this->skip_if_no_abilities_api();
		wp_set_current_user( 0 );

		// Expect the "doing it wrong" notice from WP_Ability::execute.
		$this->setExpectedIncorrectUsage( 'WP_Ability::execute' );

		$result = $this->execute_ability( 'mainwp/count-client-sites-v1', [
			'client_id_or_email' => 1,
		] );

		$this->assertWPError( $result );
		$this->assertEquals( 'ability_invalid_permissions', $result->get_error_code() );
	}

	/**
	 * Test that count-client-sites requires manage_options capability.
	 *
	 * @return void
	 */
	public function test_count_client_sites_requires_manage_options() {
		$this->skip_if_no_abilities_api();
		$this->set_current_user_as_subscriber();

		// Expect the "doing it wrong" notice from WP_Ability::execute.
		$this->setExpectedIncorrectUsage( 'WP_Ability::execute' );

		$result = $this->execute_ability( 'mainwp/count-client-sites-v1', [
			'client_id_or_email' => 1,
		] );

		$this->assertWPError( $result );
		$this->assertEquals( 'ability_invalid_permissions', $result->get_error_code() );
	}

	/**
	 * Test that count-client-sites validates input.
	 *
	 * @return void
	 */
	public function test_count_client_sites_validates_input() {
		$this->skip_if_no_abilities_api();
		$this->set_current_user_as_admin();

		$result = $this->execute_ability( 'mainwp/count-client-sites-v1', [] );

		$this->assertWPError( $result );
		$this->assertEquals( 'ability_invalid_input', $result->get_error_code() );
	}

	/**
	 * Test that count-client-sites counts multiple sites.
	 *
	 * @return void
	 */
	public function test_count_client_sites_with_multiple_sites() {
		$this->skip_if_no_abilities_api();
		$this->set_current_user_as_admin();

		$client_id = $this->create_test_client( [
			'name' => 'Client With Multiple Sites',
		] );

		for ( $i = 1; $i <= 5; $i++ ) {
			$this->create_test_site( [
				'name'      => "Site $i",
				'url'       => "https://test-site{$i}.example.com/",
				'client_id' => $client_id,
			] );
		}

		$result = $this->execute_ability( 'mainwp/count-client-sites-v1', [
			'client_id_or_email' => $client_id,
		] );

		$this->assertNotWPError( $result );
		$this->assertEquals( 5, $result['total'] );
	}

	/**
	 * Test that count-client-sites returns zero for no sites.
	 *
	 * @return void
	 */
	public function test_count_client_sites_with_no_sites() {
		$this->skip_if_no_abilities_api();
		$this->set_current_user_as_admin();

		$client_id = $this->create_test_client( [
			'name' => 'Client Without Sites',
		] );

		$result = $this->execute_ability( 'mainwp/count-client-sites-v1', [
			'client_id_or_email' => $client_id,
		] );

		$this->assertNotWPError( $result );
		$this->assertEquals( 0, $result['total'] );
	}
}
