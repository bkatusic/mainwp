<?php
/**
 * Tests for mainwp/list-tags-v1 ability.
 *
 * @package MainWP\Dashboard\Tests\Abilities
 */

namespace MainWP\Dashboard\Tests;

/**
 * Tests for mainwp/list-tags-v1 ability.
 *
 * @group abilities
 * @group abilities-tags
 */
class MainWP_List_Tags_Ability_Test extends MainWP_Abilities_Test_Case {

	/**
	 * Test that the ability is registered and discoverable.
	 *
	 * @return void
	 */
	public function test_ability_is_registered() {
		$this->skip_if_no_abilities_api();

		$abilities = wp_abilities()->get_abilities();

		$this->assertArrayHasKey(
			'mainwp/list-tags-v1',
			$abilities,
			'Ability mainwp/list-tags-v1 should be registered.'
		);
	}

	/**
	 * Test successful execution with valid input.
	 *
	 * @return void
	 */
	public function test_list_tags_returns_expected_structure() {
		$this->skip_if_no_abilities_api();
		$this->set_current_user_as_admin();

		$tag1 = $this->create_test_tag( [ 'name' => 'Production Sites', 'color' => '#e74c3c' ] );
		$tag2 = $this->create_test_tag( [ 'name' => 'Staging Sites', 'color' => '#3498db' ] );
		$tag3 = $this->create_test_tag( [ 'name' => 'Development Sites', 'color' => '#2ecc71' ] );

		$result = $this->execute_ability( 'mainwp/list-tags-v1', [] );

		$this->assertNotWPError( $result, 'Should return successful result.' );
		$this->assertIsArray( $result );
		$this->assertArrayHasKey( 'items', $result );
		$this->assertArrayHasKey( 'page', $result );
		$this->assertArrayHasKey( 'per_page', $result );
		$this->assertArrayHasKey( 'total', $result );

		$this->assertCount( 3, $result['items'], 'Should return 3 tags.' );
		$this->assertEquals( 1, $result['page'] );
		$this->assertEquals( 20, $result['per_page'] );
		$this->assertEquals( 3, $result['total'] );

		$tag = $result['items'][0];
		$this->assertArrayHasKey( 'id', $tag );
		$this->assertArrayHasKey( 'name', $tag );
		$this->assertArrayHasKey( 'color', $tag );
		$this->assertArrayHasKey( 'sites_count', $tag );
		$this->assertArrayHasKey( 'sites_ids', $tag );

		$this->assertIsInt( $tag['id'] );
		$this->assertIsString( $tag['name'] );
		$this->assertIsInt( $tag['sites_count'] );
		$this->assertIsArray( $tag['sites_ids'] );
	}

	/**
	 * Test that unauthenticated users are denied.
	 *
	 * @return void
	 */
	public function test_list_tags_requires_authentication() {
		$this->skip_if_no_abilities_api();
		$this->setExpectedIncorrectUsage( 'WP_Ability::execute' );

		wp_set_current_user( 0 );

		$result = $this->execute_ability( 'mainwp/list-tags-v1', [] );

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
	public function test_list_tags_requires_manage_options() {
		$this->skip_if_no_abilities_api();
		$this->setExpectedIncorrectUsage( 'WP_Ability::execute' );

		$subscriber_id = $this->factory->user->create( [ 'role' => 'subscriber' ] );
		wp_set_current_user( $subscriber_id );

		$result = $this->execute_ability( 'mainwp/list-tags-v1', [] );

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
	public function test_list_tags_validates_input() {
		$this->skip_if_no_abilities_api();
		$this->set_current_user_as_admin();

		$result = $this->execute_ability(
			'mainwp/list-tags-v1',
			[
				'per_page' => 9999,
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
	 * Test pagination parameters.
	 *
	 * @return void
	 */
	public function test_list_tags_pagination() {
		$this->skip_if_no_abilities_api();
		$this->set_current_user_as_admin();

		for ( $i = 1; $i <= 15; $i++ ) {
			$this->create_test_tag( [ 'name' => "Tag $i" ] );
		}

		$result = $this->execute_ability(
			'mainwp/list-tags-v1',
			[
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

	/**
	 * Test search filtering.
	 *
	 * @return void
	 */
	public function test_list_tags_search_filtering() {
		$this->skip_if_no_abilities_api();
		$this->set_current_user_as_admin();

		$this->create_test_tag( [ 'name' => 'Alpha Tag' ] );
		$this->create_test_tag( [ 'name' => 'Beta Tag' ] );

		$result = $this->execute_ability(
			'mainwp/list-tags-v1',
			[
				'search' => 'Alpha',
			]
		);

		$this->assertNotWPError( $result );
		$this->assertCount( 1, $result['items'] );
		$this->assertEquals( 'Alpha Tag', $result['items'][0]['name'] );
	}

	/**
	 * Test empty array when no tags exist.
	 *
	 * @return void
	 */
	public function test_list_tags_returns_empty_array_when_no_tags() {
		$this->skip_if_no_abilities_api();
		$this->set_current_user_as_admin();

		$result = $this->execute_ability( 'mainwp/list-tags-v1', [] );

		$this->assertNotWPError( $result );
		$this->assertIsArray( $result );
		$this->assertEmpty( $result['items'] );
		$this->assertEquals( 0, $result['total'] );
	}
}
