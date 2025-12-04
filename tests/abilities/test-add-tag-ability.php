<?php
/**
 * Tests for mainwp/add-tag-v1 ability.
 *
 * @package MainWP\Dashboard\Tests\Abilities
 */

namespace MainWP\Dashboard\Tests;

/**
 * Tests for mainwp/add-tag-v1 ability.
 *
 * @group abilities
 * @group abilities-tags
 */
class MainWP_Add_Tag_Ability_Test extends MainWP_Abilities_Test_Case {

	/**
	 * Test that the ability is registered and discoverable.
	 *
	 * @return void
	 */
	public function test_ability_is_registered() {
		$this->skip_if_no_abilities_api();

		$abilities = wp_abilities()->get_abilities();

		$this->assertArrayHasKey(
			'mainwp/add-tag-v1',
			$abilities,
			'Ability mainwp/add-tag-v1 should be registered.'
		);
	}

	/**
	 * Test successful execution with valid input.
	 *
	 * @return void
	 */
	public function test_add_tag_returns_expected_structure() {
		$this->skip_if_no_abilities_api();
		$this->set_current_user_as_admin();

		$result = $this->execute_ability(
			'mainwp/add-tag-v1',
			[
				'name'  => 'New Production Tag',
				'color' => '#9b59b6',
			]
		);

		$this->assertNotWPError( $result, 'Should return successful result.' );
		$this->assertIsArray( $result );
		$this->assertArrayHasKey( 'id', $result );
		$this->assertArrayHasKey( 'name', $result );
		$this->assertArrayHasKey( 'color', $result );
		$this->assertArrayHasKey( 'sites_count', $result );
		$this->assertArrayHasKey( 'sites_ids', $result );

		$this->assertEquals( 'New Production Tag', $result['name'] );
		$this->assertEquals( '#9b59b6', $result['color'] );
		$this->assertEquals( 0, $result['sites_count'] );
		$this->assertIsArray( $result['sites_ids'] );
	}

	/**
	 * Test that unauthenticated users are denied.
	 *
	 * @return void
	 */
	public function test_add_tag_requires_authentication() {
		$this->skip_if_no_abilities_api();
		$this->setExpectedIncorrectUsage( 'WP_Ability::execute' );

		wp_set_current_user( 0 );

		$result = $this->execute_ability(
			'mainwp/add-tag-v1',
			[
				'name' => 'Test Tag',
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
	public function test_add_tag_requires_manage_options() {
		$this->skip_if_no_abilities_api();
		$this->setExpectedIncorrectUsage( 'WP_Ability::execute' );

		$subscriber_id = $this->factory->user->create( [ 'role' => 'subscriber' ] );
		wp_set_current_user( $subscriber_id );

		$result = $this->execute_ability(
			'mainwp/add-tag-v1',
			[
				'name' => 'Test Tag',
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
	public function test_add_tag_validates_input() {
		$this->skip_if_no_abilities_api();
		$this->set_current_user_as_admin();

		$result = $this->execute_ability(
			'mainwp/add-tag-v1',
			[
				'name'  => '',
				'color' => 'invalid-color',
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
	 * Test error when creating duplicate tag name.
	 *
	 * @return void
	 */
	public function test_add_tag_prevents_duplicate_names() {
		$this->skip_if_no_abilities_api();
		$this->set_current_user_as_admin();

		$this->create_test_tag( [ 'name' => 'Existing Tag' ] );

		$result = $this->execute_ability(
			'mainwp/add-tag-v1',
			[
				'name' => 'Existing Tag',
			]
		);

		$this->assertWPError( $result );
		$this->assertEquals( 'mainwp_already_exists', $result->get_error_code() );
	}

	/**
	 * Test creating tag without color.
	 *
	 * @return void
	 */
	public function test_add_tag_without_color() {
		$this->skip_if_no_abilities_api();
		$this->set_current_user_as_admin();

		$result = $this->execute_ability(
			'mainwp/add-tag-v1',
			[
				'name' => 'Tag Without Color',
			]
		);

		$this->assertNotWPError( $result );
		$this->assertEquals( 'Tag Without Color', $result['name'] );
		$this->assertNull( $result['color'] );
	}
}
