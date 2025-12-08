<?php
/**
 * MainWP GetSitesBasic Ability Tests
 *
 * Tests for the mainwp/get-sites-basic-v1 ability.
 *
 * @package MainWP\Dashboard\Tests
 */

namespace MainWP\Dashboard\Tests;

/**
 * Tests for mainwp/get-sites-basic-v1 ability.
 *
 * @group abilities
 * @group abilities-sites
 */
class Test_GetSitesBasic_Ability extends MainWP_Abilities_Test_Case {

    /**
     * Test that the ability is registered.
     *
     * @return void
     */
    public function test_ability_is_registered() {
        $this->skip_if_no_abilities_api();

        $ability = wp_get_ability( 'mainwp/get-sites-basic-v1' );
        $this->assertNotNull( $ability, 'Ability mainwp/get-sites-basic-v1 should be registered.' );
    }

    /**
     * Test successful execution with valid input.
     *
     * Validates output structure matches get_sites_basic_output_schema():
     * - items: array of site objects with id, url, name
     * - page: current page number (integer)
     * - per_page: items per page (integer)
     * - total: total count of matching sites (integer)
     *
     * @return void
     */
    public function test_get_sites_basic_returns_expected_structure() {
        $this->skip_if_no_abilities_api();
        $this->set_current_user_as_admin();

        $site_id = $this->create_test_site( [
            'name' => 'Test Site',
            'url'  => 'https://test-get-sites-basic.example.com/',
        ] );

        $result = $this->execute_ability( 'mainwp/get-sites-basic-v1', [] );

        $this->assertNotWPError( $result, 'Should return successful result.' );
        $this->assertIsArray( $result, 'Result should be an array.' );

        // Validate pagination keys exist and are integers.
        $this->assertArrayHasKey( 'items', $result, 'Result should have items key.' );
        $this->assertArrayHasKey( 'page', $result, 'Result should have page key.' );
        $this->assertArrayHasKey( 'per_page', $result, 'Result should have per_page key.' );
        $this->assertArrayHasKey( 'total', $result, 'Result should have total key.' );

        $this->assertIsArray( $result['items'], 'items should be an array.' );
        $this->assertIsInt( $result['page'], 'page should be an integer.' );
        $this->assertIsInt( $result['per_page'], 'per_page should be an integer.' );
        $this->assertIsInt( $result['total'], 'total should be an integer.' );

        // Validate default pagination values.
        $this->assertEquals( 1, $result['page'], 'Default page should be 1.' );
        $this->assertEquals( 20, $result['per_page'], 'Default per_page should be 20.' );
        $this->assertGreaterThanOrEqual( 1, $result['total'], 'total should include at least the created test site.' );

        // Validate site structure if items exist.
        if ( ! empty( $result['items'] ) ) {
            $first_site = $result['items'][0];
            $this->assertArrayHasKey( 'id', $first_site, 'Site item should have id.' );
            $this->assertArrayHasKey( 'url', $first_site, 'Site item should have url.' );
            $this->assertArrayHasKey( 'name', $first_site, 'Site item should have name.' );
        }
    }

    /**
     * Test that unauthenticated users are denied.
     *
     * @return void
     */
    public function test_get_sites_basic_requires_authentication() {
        $this->skip_if_no_abilities_api();

        wp_set_current_user( 0 );

		// Expect the "doing it wrong" notice from WP_Ability::execute.
		$this->setExpectedIncorrectUsage( 'WP_Ability::execute' );

        $result = $this->execute_ability( 'mainwp/get-sites-basic-v1', [] );

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
    public function test_get_sites_basic_requires_manage_options() {
        $this->skip_if_no_abilities_api();

        $this->set_current_user_as_subscriber();

        $this->setExpectedIncorrectUsage( 'WP_Ability::execute' );

        $result = $this->execute_ability( 'mainwp/get-sites-basic-v1', [] );

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
    public function test_get_sites_basic_validates_input() {
        $this->skip_if_no_abilities_api();
        $this->set_current_user_as_admin();

        $site_id = $this->create_test_site();

        $result = $this->execute_ability( 'mainwp/get-sites-basic-v1', [
            'per_page' => 9999, // Exceeds maximum
        ] );

        $this->assertWPError( $result, 'Invalid input should return WP_Error.' );
    }
}
