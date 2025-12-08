<?php
/**
 * Tests for mainwp/update-tag-v1 ability.
 *
 * @package MainWP\Dashboard\Tests\Abilities
 */

namespace MainWP\Dashboard\Tests;

/**
 * Tests for mainwp/update-tag-v1 ability.
 *
 * @group abilities
 * @group abilities-tags
 */
class MainWP_Update_Tag_Ability_Test extends MainWP_Abilities_Test_Case {

	/**
	 * Test that the ability is registered and discoverable.
	 *
	 * @return void
	 */
	public function test_ability_is_registered() {
		$this->skip_if_no_abilities_api();

		$abilities = wp_get_abilities();

		$this->assertArrayHasKey(
			'mainwp/update-tag-v1',
			$abilities,
			'Ability mainwp/update-tag-v1 should be registered.'
		);
	}

	/**
	 * Test successful execution with valid input.
	 *
	 * @return void
	 */
	public function test_update_tag_returns_expected_structure() {
		$this->skip_if_no_abilities_api();
		$this->set_current_user_as_admin();

		$tag_id = $this->create_test_tag(
			[
				'name'  => 'Original Name',
				'color' => '#e74c3c',
			]
		);

		$result = $this->execute_ability(
			'mainwp/update-tag-v1',
			[
				'tag_id' => $tag_id,
				'name'   => 'Updated Name',
				'color'  => '#3498db',
			]
		);

		$this->assertNotWPError( $result, 'Should return successful result.' );
		$this->assertIsArray( $result );
		$this->assertArrayHasKey( 'id', $result );
		$this->assertArrayHasKey( 'name', $result );
		$this->assertArrayHasKey( 'color', $result );
		$this->assertArrayHasKey( 'sites_count', $result );
		$this->assertArrayHasKey( 'sites_ids', $result );

		$this->assertEquals( $tag_id, $result['id'] );
		$this->assertEquals( 'Updated Name', $result['name'] );
		$this->assertEquals( '#3498db', $result['color'] );
	}

	/**
	 * Test that unauthenticated users are denied.
	 *
	 * @return void
	 */
	public function test_update_tag_requires_authentication() {
		$this->skip_if_no_abilities_api();
		$this->setExpectedIncorrectUsage( 'WP_Ability::execute' );

		wp_set_current_user( 0 );

		$result = $this->execute_ability(
			'mainwp/update-tag-v1',
			[
				'tag_id' => 1,
				'name'   => 'Test',
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
	public function test_update_tag_requires_manage_options() {
		$this->skip_if_no_abilities_api();
		$this->setExpectedIncorrectUsage( 'WP_Ability::execute' );

		$subscriber_id = $this->factory->user->create( [ 'role' => 'subscriber' ] );
		wp_set_current_user( $subscriber_id );

		$result = $this->execute_ability(
			'mainwp/update-tag-v1',
			[
				'tag_id' => 1,
				'name'   => 'Test',
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
	public function test_update_tag_validates_input() {
		$this->skip_if_no_abilities_api();
		$this->set_current_user_as_admin();

		$result = $this->execute_ability(
			'mainwp/update-tag-v1',
			[
				'tag_id' => 'invalid',
				'name'   => 'Test',
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
	public function test_update_tag_returns_error_for_not_found() {
		$this->skip_if_no_abilities_api();
		$this->set_current_user_as_admin();

		$result = $this->execute_ability(
			'mainwp/update-tag-v1',
			[
				'tag_id' => 999999,
				'name'   => 'Updated Name',
			]
		);

		$this->assertWPError( $result );
		$this->assertEquals( 'mainwp_tag_not_found', $result->get_error_code() );
	}

	/**
	 * Test updating only name.
	 *
	 * @return void
	 */
	public function test_update_tag_name_only() {
		$this->skip_if_no_abilities_api();
		$this->set_current_user_as_admin();

		$tag_id = $this->create_test_tag(
			[
				'name'  => 'Original Name',
				'color' => '#e74c3c',
			]
		);

		$result = $this->execute_ability(
			'mainwp/update-tag-v1',
			[
				'tag_id' => $tag_id,
				'name'   => 'Updated Name Only',
			]
		);

		$this->assertNotWPError( $result );
		$this->assertEquals( 'Updated Name Only', $result['name'] );
		$this->assertEquals( '#e74c3c', $result['color'] );
	}

	/**
	 * Test updating only color.
	 *
	 * @return void
	 */
	public function test_update_tag_color_only() {
		$this->skip_if_no_abilities_api();
		$this->set_current_user_as_admin();

		$tag_id = $this->create_test_tag(
			[
				'name'  => 'Original Name',
				'color' => '#e74c3c',
			]
		);

		$result = $this->execute_ability(
			'mainwp/update-tag-v1',
			[
				'tag_id' => $tag_id,
				'color'  => '#9b59b6',
			]
		);

		$this->assertNotWPError( $result );
		$this->assertEquals( 'Original Name', $result['name'] );
		$this->assertEquals( '#9b59b6', $result['color'] );
	}
}
