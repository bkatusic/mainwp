<?php
/**
 * Tests for mainwp/get-tag-clients-v1 ability.
 *
 * @package MainWP\Dashboard\Tests\Abilities
 */

namespace MainWP\Dashboard\Tests;

use MainWP\Dashboard\MainWP_DB_Client;

/**
 * Tests for mainwp/get-tag-clients-v1 ability.
 *
 * @group abilities
 * @group abilities-tags
 */
class MainWP_Get_Tag_Clients_Ability_Test extends MainWP_Abilities_Test_Case {

	/**
	 * Test that the ability is registered and discoverable.
	 *
	 * @return void
	 */
	public function test_ability_is_registered() {
		$this->skip_if_no_abilities_api();

		$abilities = wp_get_abilities();

		$this->assertArrayHasKey(
			'mainwp/get-tag-clients-v1',
			$abilities,
			'Ability mainwp/get-tag-clients-v1 should be registered.'
		);
	}

	/**
	 * Test successful execution with valid input.
	 *
	 * @return void
	 */
	public function test_get_tag_clients_returns_expected_structure() {
		$this->skip_if_no_abilities_api();
		$this->set_current_user_as_admin();

		$tag_id   = $this->create_test_tag( [ 'name' => 'VIP Clients' ] );
		$client1  = $this->create_test_client(
			[
				'name'   => 'Client 1',
				'groups' => [ $tag_id ],
			]
		);
		$client2  = $this->create_test_client(
			[
				'name'   => 'Client 2',
				'groups' => [ $tag_id ],
			]
		);

		$result = $this->execute_ability(
			'mainwp/get-tag-clients-v1',
			[
				'tag_id' => $tag_id,
			]
		);

		$this->assertNotWPError( $result, 'Should return successful result.' );
		$this->assertIsArray( $result );
		$this->assertArrayHasKey( 'items', $result );
		$this->assertArrayHasKey( 'page', $result );
		$this->assertArrayHasKey( 'per_page', $result );
		$this->assertArrayHasKey( 'total', $result );

		$this->assertCount( 2, $result['items'] );
		$this->assertEquals( 2, $result['total'] );
		$this->assertEquals( 1, $result['page'] );
		$this->assertEquals( 20, $result['per_page'] );

		$client = $result['items'][0];
		$this->assertArrayHasKey( 'id', $client );
		$this->assertArrayHasKey( 'name', $client );
	}

	/**
	 * Test that unauthenticated users are denied.
	 *
	 * @return void
	 */
	public function test_get_tag_clients_requires_authentication() {
		$this->skip_if_no_abilities_api();
		$this->setExpectedIncorrectUsage( 'WP_Ability::execute' );

		wp_set_current_user( 0 );

		$result = $this->execute_ability(
			'mainwp/get-tag-clients-v1',
			[
				'tag_id' => 1,
			]
		);

		$this->assertWPError( $result, 'Unauthenticated request should return WP_Error.' );
		$this->assertEquals(
			'ability_invalid_permissions',
			$result->get_error_code(),
			'Should return ability_invalid_permissions error code.'
		);
	}

	/**
	 * Test that users without manage_options capability are denied.
	 *
	 * @return void
	 */
	public function test_get_tag_clients_requires_manage_options() {
		$this->skip_if_no_abilities_api();
		$this->setExpectedIncorrectUsage( 'WP_Ability::execute' );

		$subscriber_id = $this->factory->user->create( [ 'role' => 'subscriber' ] );
		wp_set_current_user( $subscriber_id );

		$result = $this->execute_ability(
			'mainwp/get-tag-clients-v1',
			[
				'tag_id' => 1,
			]
		);

		$this->assertWPError( $result, 'Subscriber should be denied.' );
		$this->assertEquals(
			'ability_invalid_permissions',
			$result->get_error_code(),
			'Should return ability_invalid_permissions error code.'
		);
	}

	/**
	 * Test input validation rejects invalid values.
	 *
	 * @return void
	 */
	public function test_get_tag_clients_validates_input() {
		$this->skip_if_no_abilities_api();
		$this->set_current_user_as_admin();

		$result = $this->execute_ability(
			'mainwp/get-tag-clients-v1',
			[
				'tag_id' => 'invalid',
			]
		);

		$this->assertWPError( $result, 'Invalid input should return WP_Error.' );
		$this->assertEquals(
			'ability_invalid_input',
			$result->get_error_code(),
			'Should return ability_invalid_input for schema validation failure.'
		);
	}

	/**
	 * Test error for non-existent tag.
	 *
	 * @return void
	 */
	public function test_get_tag_clients_returns_error_for_not_found() {
		$this->skip_if_no_abilities_api();
		$this->set_current_user_as_admin();

		$result = $this->execute_ability(
			'mainwp/get-tag-clients-v1',
			[
				'tag_id' => 999999,
			]
		);

		$this->assertWPError( $result );
		$this->assertEquals( 'mainwp_tag_not_found', $result->get_error_code() );
	}

	/**
	 * Test empty array when tag has no clients.
	 *
	 * @return void
	 */
	public function test_get_tag_clients_returns_empty_array_when_no_clients() {
		$this->skip_if_no_abilities_api();
		$this->set_current_user_as_admin();

		$tag_id = $this->create_test_tag( [ 'name' => 'Empty Tag' ] );

		$result = $this->execute_ability(
			'mainwp/get-tag-clients-v1',
			[
				'tag_id' => $tag_id,
			]
		);

		$this->assertNotWPError( $result );
		$this->assertEmpty( $result['items'] );
		$this->assertEquals( 0, $result['total'] );
	}

	/**
	 * Test pagination.
	 *
	 * @return void
	 */
	public function test_get_tag_clients_pagination() {
		$this->skip_if_no_abilities_api();
		$this->set_current_user_as_admin();

		$tag_id = $this->create_test_tag( [ 'name' => 'Many Clients' ] );

		for ( $i = 1; $i <= 15; $i++ ) {
			$this->create_test_client(
				[
					'name'   => "Client $i",
					'groups' => [ $tag_id ],
				]
			);
		}

		$result = $this->execute_ability(
			'mainwp/get-tag-clients-v1',
			[
				'tag_id'   => $tag_id,
				'page'     => 2,
				'per_page' => 5,
			]
		);

		$this->assertNotWPError( $result );
		$this->assertCount( 5, $result['items'] );
		$this->assertEquals( 2, $result['page'] );
		$this->assertEquals( 5, $result['per_page'] );
		$this->assertEquals( 15, $result['total'] );
	}
}
