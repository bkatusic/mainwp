<?php
/**
 * Tests for mainwp/delete-tag-v1 ability.
 *
 * @package MainWP\Dashboard\Tests\Abilities
 */

namespace MainWP\Dashboard\Tests;

/**
 * Tests for mainwp/delete-tag-v1 ability.
 *
 * @group abilities
 * @group abilities-tags
 */
class MainWP_Delete_Tag_Ability_Test extends MainWP_Abilities_Test_Case {

	/**
	 * Test that the ability is registered and discoverable.
	 *
	 * @return void
	 */
	public function test_ability_is_registered() {
		$this->skip_if_no_abilities_api();

		$abilities = wp_get_abilities();

		$this->assertArrayHasKey(
			'mainwp/delete-tag-v1',
			$abilities,
			'Ability mainwp/delete-tag-v1 should be registered.'
		);
	}

	/**
	 * Test successful execution with valid input and confirmation.
	 *
	 * @return void
	 */
	public function test_delete_tag_returns_expected_structure() {
		$this->skip_if_no_abilities_api();
		$this->set_current_user_as_admin();

		$tag_id = $this->create_test_tag( [ 'name' => 'Tag To Delete' ] );

		$result = $this->execute_ability(
			'mainwp/delete-tag-v1',
			[
				'tag_id'  => $tag_id,
				'confirm' => true,
			]
		);

		$this->assertNotWPError( $result, 'Should return successful result.' );
		$this->assertIsArray( $result );
		$this->assertArrayHasKey( 'deleted', $result );
		$this->assertArrayHasKey( 'tag', $result );
		$this->assertTrue( $result['deleted'] );
		$this->assertEquals( $tag_id, $result['tag']['id'] );
		$this->assertEquals( 'Tag To Delete', $result['tag']['name'] );
	}

	/**
	 * Test that unauthenticated users are denied.
	 *
	 * @return void
	 */
	public function test_delete_tag_requires_authentication() {
		$this->skip_if_no_abilities_api();
		$this->setExpectedIncorrectUsage( 'WP_Ability::execute' );

		wp_set_current_user( 0 );

		$result = $this->execute_ability(
			'mainwp/delete-tag-v1',
			[
				'tag_id'  => 1,
				'confirm' => true,
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
	public function test_delete_tag_requires_manage_options() {
		$this->skip_if_no_abilities_api();
		$this->setExpectedIncorrectUsage( 'WP_Ability::execute' );

		$subscriber_id = $this->factory->user->create( [ 'role' => 'subscriber' ] );
		wp_set_current_user( $subscriber_id );

		$result = $this->execute_ability(
			'mainwp/delete-tag-v1',
			[
				'tag_id'  => 1,
				'confirm' => true,
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
	public function test_delete_tag_validates_input() {
		$this->skip_if_no_abilities_api();
		$this->set_current_user_as_admin();

		$result = $this->execute_ability(
			'mainwp/delete-tag-v1',
			[
				'tag_id'  => 'invalid',
				'confirm' => true,
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
	public function test_delete_tag_returns_error_for_not_found() {
		$this->skip_if_no_abilities_api();
		$this->set_current_user_as_admin();

		$result = $this->execute_ability(
			'mainwp/delete-tag-v1',
			[
				'tag_id'  => 999999,
				'confirm' => true,
			]
		);

		$this->assertWPError( $result );
		$this->assertEquals( 'mainwp_tag_not_found', $result->get_error_code() );
	}

	/**
	 * Test confirmation is required.
	 *
	 * @return void
	 */
	public function test_delete_tag_requires_confirmation() {
		$this->skip_if_no_abilities_api();
		$this->set_current_user_as_admin();

		$tag_id = $this->create_test_tag( [ 'name' => 'Tag Requiring Confirmation' ] );

		$result = $this->execute_ability(
			'mainwp/delete-tag-v1',
			[
				'tag_id' => $tag_id,
			]
		);

		$this->assertWPError( $result );
		$this->assertEquals( 'mainwp_confirmation_required', $result->get_error_code() );
	}

	/**
	 * Test dry run mode.
	 *
	 * @return void
	 */
	public function test_delete_tag_dry_run() {
		$this->skip_if_no_abilities_api();
		$this->set_current_user_as_admin();

		$tag_id = $this->create_test_tag( [ 'name' => 'Tag For Dry Run' ] );

		$result = $this->execute_ability(
			'mainwp/delete-tag-v1',
			[
				'tag_id'  => $tag_id,
				'dry_run' => true,
			]
		);

		$this->assertNotWPError( $result );
		$this->assertArrayHasKey( 'dry_run', $result );
		$this->assertArrayHasKey( 'would_affect', $result );
		$this->assertTrue( $result['dry_run'] );
		$this->assertEquals( $tag_id, $result['would_affect']['id'] );
		$this->assertEquals( 'Tag For Dry Run', $result['would_affect']['name'] );
		$this->assertArrayHasKey( 'sites_count', $result['would_affect'] );
		$this->assertArrayHasKey( 'clients_count', $result['would_affect'] );
		$this->assertArrayHasKey( 'warnings', $result );
		$this->assertIsArray( $result['warnings'] );
	}

	/**
	 * Test dry_run and confirm cannot both be true.
	 *
	 * @return void
	 */
	public function test_delete_tag_prevents_dry_run_with_confirm() {
		$this->skip_if_no_abilities_api();
		$this->set_current_user_as_admin();

		$tag_id = $this->create_test_tag( [ 'name' => 'Conflicting Options' ] );

		$result = $this->execute_ability(
			'mainwp/delete-tag-v1',
			[
				'tag_id'  => $tag_id,
				'confirm' => true,
				'dry_run' => true,
			]
		);

		$this->assertWPError( $result );
		$this->assertEquals( 'mainwp_invalid_input', $result->get_error_code() );
	}

	/**
	 * Test that deleting a tag removes site associations.
	 *
	 * @return void
	 */
	public function test_delete_tag_removes_site_associations() {
		$this->skip_if_no_abilities_api();
		$this->set_current_user_as_admin();

		// Create a tag.
		$tag_id = $this->create_test_tag( [ 'name' => 'Tag With Sites' ] );

		// Create test sites.
		$site_id_1 = $this->create_test_site( [ 'name' => 'Site 1 for Tag' ] );
		$site_id_2 = $this->create_test_site( [ 'name' => 'Site 2 for Tag' ] );

		// Associate sites with the tag.
		\MainWP\Dashboard\MainWP_DB_Common::instance()->update_group_site( $tag_id, $site_id_1 );
		\MainWP\Dashboard\MainWP_DB_Common::instance()->update_group_site( $tag_id, $site_id_2 );

		// Verify sites are associated with the tag before deletion.
		$sites_before = \MainWP\Dashboard\MainWP_DB::instance()->get_websites_by_group_id( $tag_id );
		$this->assertIsArray( $sites_before, 'Sites should be associated with tag before deletion.' );
		$this->assertCount( 2, $sites_before, 'Two sites should be associated with tag before deletion.' );

		// Delete the tag.
		$result = $this->execute_ability(
			'mainwp/delete-tag-v1',
			[
				'tag_id'  => $tag_id,
				'confirm' => true,
			]
		);

		$this->assertNotWPError( $result, 'Tag deletion should succeed.' );
		$this->assertTrue( $result['deleted'], 'Tag should be marked as deleted.' );

		// Verify site associations are removed after deletion.
		$sites_after = \MainWP\Dashboard\MainWP_DB::instance()->get_websites_by_group_id( $tag_id );
		$this->assertEmpty( $sites_after, 'Site associations should be removed after tag deletion.' );
	}

	/**
	 * Test that deleting a tag removes its association from clients (via sites).
	 *
	 * Clients are associated with tags through their sites. When a tag is deleted,
	 * clients should no longer be findable by that tag.
	 *
	 * @return void
	 */
	public function test_delete_tag_removes_client_tag_association() {
		$this->skip_if_no_abilities_api();
		$this->set_current_user_as_admin();

		// Create a tag.
		$tag_id = $this->create_test_tag( [ 'name' => 'Tag With Client Sites' ] );

		// Create a client.
		$client_id = $this->create_test_client( [ 'name' => 'Client For Tag Test' ] );

		// Create a site assigned to the client.
		$site_id = $this->create_test_site(
			[
				'name'      => 'Site For Client Tag Test',
				'client_id' => $client_id,
			]
		);

		// Associate the site with the tag.
		\MainWP\Dashboard\MainWP_DB_Common::instance()->update_group_site( $tag_id, $site_id );

		// Verify client is findable by the tag before deletion.
		$clients_before = \MainWP\Dashboard\MainWP_DB_Client::instance()->get_wp_client_by(
			'all',
			null,
			OBJECT,
			[ 'by_tags' => [ $tag_id ] ]
		);
		$this->assertIsArray( $clients_before, 'Clients should be findable by tag before deletion.' );
		$this->assertNotEmpty( $clients_before, 'At least one client should be associated with tag before deletion.' );

		// Delete the tag.
		$result = $this->execute_ability(
			'mainwp/delete-tag-v1',
			[
				'tag_id'  => $tag_id,
				'confirm' => true,
			]
		);

		$this->assertNotWPError( $result, 'Tag deletion should succeed.' );
		$this->assertTrue( $result['deleted'], 'Tag should be marked as deleted.' );

		// Verify client is no longer findable by the tag after deletion.
		$clients_after = \MainWP\Dashboard\MainWP_DB_Client::instance()->get_wp_client_by(
			'all',
			null,
			OBJECT,
			[ 'by_tags' => [ $tag_id ] ]
		);
		$this->assertEmpty( $clients_after, 'No clients should be associated with tag after deletion.' );
	}
}
