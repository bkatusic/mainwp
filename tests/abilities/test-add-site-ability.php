<?php
/**
 * MainWP AddSite Ability Tests
 *
 * Tests for the mainwp/add-site-v1 ability.
 *
 * @package MainWP\Dashboard\Tests
 */

namespace MainWP\Dashboard\Tests;

/**
 * Tests for mainwp/add-site-v1 ability.
 *
 * @group abilities
 * @group abilities-sites
 */
class Test_AddSite_Ability extends MainWP_Abilities_Test_Case {

    /**
     * Test that the ability is registered.
     *
     * @return void
     */
    public function test_ability_is_registered() {
        $this->skip_if_no_abilities_api();

        $ability = wp_get_ability( 'mainwp/add-site-v1' );
        $this->assertNotNull( $ability, 'Ability mainwp/add-site-v1 should be registered.' );
    }

    /**
     * Test successful execution with valid input.
     *
     * @return void
     */
    public function test_add_site_returns_expected_structure() {
        $this->skip_if_no_abilities_api();
        $this->set_current_user_as_admin();

        $site_id = $this->create_test_site( [
            'name' => 'Test Site',
            'url'  => 'https://test-add-site.example.com/',
        ] );

        $result = $this->execute_ability( 'mainwp/add-site-v1', [
            'url'            => 'https://example.com',
            'name'           => 'Test Site',
            'admin_username' => 'admin',
        ] );

        $this->assertNotWPError( $result, 'Should return successful result.' );
        $this->assertIsArray( $result );
        $this->assertArrayHasKey('id', $result);
        $this->assertArrayHasKey('url', $result);
        $this->assertArrayHasKey('name', $result);
    }

    /**
     * Test that unauthenticated users are denied.
     *
     * @return void
     */
    public function test_add_site_requires_authentication() {
        $this->skip_if_no_abilities_api();

        wp_set_current_user( 0 );

        $result = $this->execute_ability( 'mainwp/add-site-v1', [] );

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
    public function test_add_site_requires_manage_options() {
        $this->skip_if_no_abilities_api();

        $subscriber_id = $this->factory->user->create( [ 'role' => 'subscriber' ] );
        wp_set_current_user( $subscriber_id );

        $result = $this->execute_ability( 'mainwp/add-site-v1', [] );

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
    public function test_add_site_validates_input() {
        $this->skip_if_no_abilities_api();
        $this->set_current_user_as_admin();

        $site_id = $this->create_test_site();

        $result = $this->execute_ability( 'mainwp/add-site-v1', [
            'url' => '', // Empty required field
        ] );

        $this->assertWPError( $result, 'Invalid input should return WP_Error.' );
    }

    /**
     * Test that URL normalization catches case-insensitive duplicates.
     *
     * URLs that differ only by case should be treated as duplicates.
     * Per RFC 4343, domain names are case-insensitive.
     *
     * @return void
     */
    public function test_add_site_rejects_case_variant_duplicate() {
        $this->skip_if_no_abilities_api();
        $this->set_current_user_as_admin();

        // Create a site with lowercase URL.
        $site_id = $this->create_test_site( [
            'name' => 'Original Site',
            'url'  => 'https://test-case-duplicate.example.com/',
        ] );

        // Try to add a site with uppercase URL variation.
        $result = $this->execute_ability( 'mainwp/add-site-v1', [
            'url'            => 'https://TEST-CASE-DUPLICATE.EXAMPLE.COM/',
            'name'           => 'Duplicate Site',
            'admin_username' => 'admin',
        ] );

        $this->assertWPError( $result, 'Case-variant URL should be rejected as duplicate.' );
        $this->assertEquals(
            'mainwp_site_already_exists',
            $result->get_error_code(),
            'Should return mainwp_site_already_exists error code for case-variant duplicate.'
        );
    }

    /**
     * Test that URL normalization catches trailing slash variants.
     *
     * URLs that differ only by trailing slash should be treated as duplicates.
     *
     * @return void
     */
    public function test_add_site_rejects_trailing_slash_variant_duplicate() {
        $this->skip_if_no_abilities_api();
        $this->set_current_user_as_admin();

        // Create a site with trailing slash.
        $site_id = $this->create_test_site( [
            'name' => 'Original Site',
            'url'  => 'https://test-slash-duplicate.example.com/',
        ] );

        // Try to add a site without trailing slash.
        $result = $this->execute_ability( 'mainwp/add-site-v1', [
            'url'            => 'https://test-slash-duplicate.example.com',
            'name'           => 'Duplicate Site',
            'admin_username' => 'admin',
        ] );

        $this->assertWPError( $result, 'Trailing slash variant URL should be rejected as duplicate.' );
        $this->assertEquals(
            'mainwp_site_already_exists',
            $result->get_error_code(),
            'Should return mainwp_site_already_exists error code for trailing slash variant.'
        );
    }
}
