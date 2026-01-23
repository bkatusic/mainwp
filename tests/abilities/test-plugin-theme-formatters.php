<?php
/**
 * MainWP Plugin/Theme Formatter Tests
 *
 * Tests for MainWP_Abilities_Util::format_plugin_for_output() and
 * MainWP_Abilities_Util::format_theme_for_output() utility methods.
 *
 * These formatters normalize plugin/theme data for consistent API output.
 *
 * @package MainWP\Dashboard\Tests
 */

namespace MainWP\Dashboard\Tests;

use MainWP\Dashboard\MainWP_Abilities_Util;

/**
 * Tests for plugin and theme formatting utility methods.
 *
 * @group abilities
 * @group abilities-util
 */
class MainWP_Plugin_Theme_Formatters_Test extends MainWP_Abilities_Test_Case {

	// =========================================================================
	// format_plugin_for_output() Tests
	// =========================================================================

	/**
	 * Test format_plugin_for_output() with full data using capitalized keys.
	 *
	 * @return void
	 */
	public function test_format_plugin_with_full_data_capitalized_keys() {
		$plugin = [
			'slug'    => 'akismet/akismet.php',
			'Name'    => 'Akismet Anti-spam',
			'Version' => '5.3.1',
			'active'  => 1,
			'update'  => [
				'new_version' => '5.3.2',
			],
		];

		$result = MainWP_Abilities_Util::format_plugin_for_output( $plugin );

		$this->assertIsArray( $result, 'Result should be an array.' );
		$this->assertEquals( 'akismet/akismet.php', $result['slug'], 'Slug should match.' );
		$this->assertEquals( 'Akismet Anti-spam', $result['name'], 'Name should match.' );
		$this->assertEquals( '5.3.1', $result['version'], 'Version should match.' );
		$this->assertTrue( $result['active'], 'Active should be true.' );
		$this->assertTrue( $result['has_update'], 'Has update should be true.' );
		$this->assertEquals( '5.3.2', $result['update_version'], 'Update version should match.' );
	}

	/**
	 * Test format_plugin_for_output() with lowercase keys.
	 *
	 * @return void
	 */
	public function test_format_plugin_with_lowercase_keys() {
		$plugin = [
			'slug'    => 'jetpack/jetpack.php',
			'name'    => 'Jetpack',
			'version' => '13.0',
			'active'  => true,
		];

		$result = MainWP_Abilities_Util::format_plugin_for_output( $plugin );

		$this->assertEquals( 'jetpack/jetpack.php', $result['slug'], 'Slug should match.' );
		$this->assertEquals( 'Jetpack', $result['name'], 'Name should be extracted from lowercase key.' );
		$this->assertEquals( '13.0', $result['version'], 'Version should be extracted from lowercase key.' );
		$this->assertTrue( $result['active'], 'Active should be true.' );
		$this->assertFalse( $result['has_update'], 'Has update should be false when no update data.' );
		$this->assertNull( $result['update_version'], 'Update version should be null.' );
	}

	/**
	 * Test format_plugin_for_output() with missing optional keys.
	 *
	 * @return void
	 */
	public function test_format_plugin_with_missing_optional_keys() {
		$plugin = [
			'slug' => 'hello-dolly/hello.php',
		];

		$result = MainWP_Abilities_Util::format_plugin_for_output( $plugin );

		$this->assertEquals( 'hello-dolly/hello.php', $result['slug'], 'Slug should match.' );
		$this->assertEquals( 'hello-dolly/hello.php', $result['name'], 'Name should fall back to slug.' );
		$this->assertEquals( '', $result['version'], 'Version should default to empty string.' );
		$this->assertFalse( $result['active'], 'Active should default to false.' );
		$this->assertFalse( $result['has_update'], 'Has update should be false.' );
		$this->assertNull( $result['update_version'], 'Update version should be null.' );
	}

	/**
	 * Test format_plugin_for_output() with non-boolean active values.
	 *
	 * @return void
	 */
	public function test_format_plugin_coerces_active_to_boolean() {
		// Test integer 1 (common from database).
		$plugin1 = [ 'slug' => 'plugin-a', 'active' => 1 ];
		$result1 = MainWP_Abilities_Util::format_plugin_for_output( $plugin1 );
		$this->assertTrue( $result1['active'], 'Integer 1 should coerce to true.' );

		// Test integer 0.
		$plugin2 = [ 'slug' => 'plugin-b', 'active' => 0 ];
		$result2 = MainWP_Abilities_Util::format_plugin_for_output( $plugin2 );
		$this->assertFalse( $result2['active'], 'Integer 0 should coerce to false.' );

		// Test string "1".
		$plugin3 = [ 'slug' => 'plugin-c', 'active' => '1' ];
		$result3 = MainWP_Abilities_Util::format_plugin_for_output( $plugin3 );
		$this->assertTrue( $result3['active'], 'String "1" should coerce to true.' );

		// Test string "0".
		$plugin4 = [ 'slug' => 'plugin-d', 'active' => '0' ];
		$result4 = MainWP_Abilities_Util::format_plugin_for_output( $plugin4 );
		$this->assertFalse( $result4['active'], 'String "0" should coerce to false.' );

		// Test empty string.
		$plugin5 = [ 'slug' => 'plugin-e', 'active' => '' ];
		$result5 = MainWP_Abilities_Util::format_plugin_for_output( $plugin5 );
		$this->assertFalse( $result5['active'], 'Empty string should coerce to false.' );
	}

	/**
	 * Test format_plugin_for_output() with update data in different structures.
	 *
	 * @return void
	 */
	public function test_format_plugin_handles_various_update_structures() {
		// Test update with new_version directly on plugin.
		$plugin1 = [
			'slug'        => 'plugin-a',
			'update'      => true, // Simple truthy value.
			'new_version' => '2.0.0',
		];
		$result1 = MainWP_Abilities_Util::format_plugin_for_output( $plugin1 );
		$this->assertTrue( $result1['has_update'], 'Has update should be true.' );
		$this->assertEquals( '2.0.0', $result1['update_version'], 'Should extract new_version from plugin root.' );

		// Test nested update structure.
		$plugin2 = [
			'slug'   => 'plugin-b',
			'update' => [
				'new_version' => '3.0.0',
			],
		];
		$result2 = MainWP_Abilities_Util::format_plugin_for_output( $plugin2 );
		$this->assertTrue( $result2['has_update'], 'Has update should be true.' );
		$this->assertEquals( '3.0.0', $result2['update_version'], 'Should extract new_version from nested update.' );

		// Test empty update array (edge case).
		$plugin3 = [
			'slug'   => 'plugin-c',
			'update' => [],
		];
		$result3 = MainWP_Abilities_Util::format_plugin_for_output( $plugin3 );
		$this->assertFalse( $result3['has_update'], 'Empty update array should be false.' );
	}

	/**
	 * Test format_plugin_for_output() with object input.
	 *
	 * @return void
	 */
	public function test_format_plugin_handles_object_input() {
		$plugin = (object) [
			'slug'    => 'woocommerce/woocommerce.php',
			'Name'    => 'WooCommerce',
			'Version' => '8.5.0',
			'active'  => 1,
		];

		$result = MainWP_Abilities_Util::format_plugin_for_output( $plugin );

		$this->assertIsArray( $result, 'Result should be an array.' );
		$this->assertEquals( 'woocommerce/woocommerce.php', $result['slug'], 'Slug should match.' );
		$this->assertEquals( 'WooCommerce', $result['name'], 'Name should match.' );
	}

	/**
	 * Test format_plugin_for_output() with non-array/non-object input.
	 *
	 * @return void
	 */
	public function test_format_plugin_handles_invalid_input() {
		// Test null input.
		$result1 = MainWP_Abilities_Util::format_plugin_for_output( null );
		$this->assertIsArray( $result1, 'Null input should return array.' );
		$this->assertArrayHasKey( 'slug', $result1, 'Should have slug key.' );

		// Test string input.
		$result2 = MainWP_Abilities_Util::format_plugin_for_output( 'not-an-array' );
		$this->assertIsArray( $result2, 'String input should return array.' );

		// Test integer input.
		$result3 = MainWP_Abilities_Util::format_plugin_for_output( 123 );
		$this->assertIsArray( $result3, 'Integer input should return array.' );
	}

	/**
	 * Test format_plugin_for_output() always returns expected keys.
	 *
	 * @return void
	 */
	public function test_format_plugin_always_returns_expected_keys() {
		$expected_keys = [ 'slug', 'name', 'version', 'active', 'has_update', 'update_version' ];

		// Test with empty array.
		$result = MainWP_Abilities_Util::format_plugin_for_output( [] );

		foreach ( $expected_keys as $key ) {
			$this->assertArrayHasKey( $key, $result, "Result should always have '{$key}' key." );
		}
	}

	/**
	 * Test format_plugin_for_output() sanitizes output.
	 *
	 * @return void
	 */
	public function test_format_plugin_sanitizes_output() {
		$plugin = [
			'slug'    => '<script>alert("xss")</script>',
			'Name'    => '<img src=x onerror=alert("xss")>',
			'Version' => '1.0<script>',
		];

		$result = MainWP_Abilities_Util::format_plugin_for_output( $plugin );

		$this->assertStringNotContainsString( '<script>', $result['slug'], 'Slug should be sanitized.' );
		$this->assertStringNotContainsString( '<img', $result['name'], 'Name should be sanitized.' );
		$this->assertStringNotContainsString( '<script>', $result['version'], 'Version should be sanitized.' );
	}

	// =========================================================================
	// format_theme_for_output() Tests
	// =========================================================================

	/**
	 * Test format_theme_for_output() with full data using capitalized keys.
	 *
	 * @return void
	 */
	public function test_format_theme_with_full_data_capitalized_keys() {
		$theme = [
			'slug'    => 'twentytwentyfour',
			'Name'    => 'Twenty Twenty-Four',
			'Version' => '1.1',
			'active'  => 1,
			'update'  => [
				'new_version' => '1.2',
			],
		];

		$result = MainWP_Abilities_Util::format_theme_for_output( $theme );

		$this->assertIsArray( $result, 'Result should be an array.' );
		$this->assertEquals( 'twentytwentyfour', $result['slug'], 'Slug should match.' );
		$this->assertEquals( 'Twenty Twenty-Four', $result['name'], 'Name should match.' );
		$this->assertEquals( '1.1', $result['version'], 'Version should match.' );
		$this->assertTrue( $result['active'], 'Active should be true.' );
		$this->assertTrue( $result['has_update'], 'Has update should be true.' );
		$this->assertEquals( '1.2', $result['update_version'], 'Update version should match.' );
	}

	/**
	 * Test format_theme_for_output() with lowercase keys.
	 *
	 * @return void
	 */
	public function test_format_theme_with_lowercase_keys() {
		$theme = [
			'slug'    => 'astra',
			'name'    => 'Astra',
			'version' => '4.5.0',
			'active'  => false,
		];

		$result = MainWP_Abilities_Util::format_theme_for_output( $theme );

		$this->assertEquals( 'astra', $result['slug'], 'Slug should match.' );
		$this->assertEquals( 'Astra', $result['name'], 'Name should be extracted from lowercase key.' );
		$this->assertEquals( '4.5.0', $result['version'], 'Version should be extracted from lowercase key.' );
		$this->assertFalse( $result['active'], 'Active should be false.' );
	}

	/**
	 * Test format_theme_for_output() with missing optional keys.
	 *
	 * @return void
	 */
	public function test_format_theme_with_missing_optional_keys() {
		$theme = [
			'slug' => 'storefront',
		];

		$result = MainWP_Abilities_Util::format_theme_for_output( $theme );

		$this->assertEquals( 'storefront', $result['slug'], 'Slug should match.' );
		$this->assertEquals( 'storefront', $result['name'], 'Name should fall back to slug.' );
		$this->assertEquals( '', $result['version'], 'Version should default to empty string.' );
		$this->assertFalse( $result['active'], 'Active should default to false.' );
		$this->assertFalse( $result['has_update'], 'Has update should be false.' );
		$this->assertNull( $result['update_version'], 'Update version should be null.' );
	}

	/**
	 * Test format_theme_for_output() coerces active to boolean.
	 *
	 * @return void
	 */
	public function test_format_theme_coerces_active_to_boolean() {
		// Test integer 1 (common from database).
		$theme1 = [ 'slug' => 'theme-a', 'active' => 1 ];
		$result1 = MainWP_Abilities_Util::format_theme_for_output( $theme1 );
		$this->assertTrue( $result1['active'], 'Integer 1 should coerce to true.' );

		// Test integer 0.
		$theme2 = [ 'slug' => 'theme-b', 'active' => 0 ];
		$result2 = MainWP_Abilities_Util::format_theme_for_output( $theme2 );
		$this->assertFalse( $result2['active'], 'Integer 0 should coerce to false.' );

		// Test string "1".
		$theme3 = [ 'slug' => 'theme-c', 'active' => '1' ];
		$result3 = MainWP_Abilities_Util::format_theme_for_output( $theme3 );
		$this->assertTrue( $result3['active'], 'String "1" should coerce to true.' );
	}

	/**
	 * Test format_theme_for_output() handles various update structures.
	 *
	 * @return void
	 */
	public function test_format_theme_handles_various_update_structures() {
		// Test update with new_version directly on theme.
		$theme1 = [
			'slug'        => 'theme-a',
			'update'      => true,
			'new_version' => '2.0.0',
		];
		$result1 = MainWP_Abilities_Util::format_theme_for_output( $theme1 );
		$this->assertTrue( $result1['has_update'], 'Has update should be true.' );
		$this->assertEquals( '2.0.0', $result1['update_version'], 'Should extract new_version from theme root.' );

		// Test nested update structure.
		$theme2 = [
			'slug'   => 'theme-b',
			'update' => [
				'new_version' => '3.0.0',
			],
		];
		$result2 = MainWP_Abilities_Util::format_theme_for_output( $theme2 );
		$this->assertTrue( $result2['has_update'], 'Has update should be true.' );
		$this->assertEquals( '3.0.0', $result2['update_version'], 'Should extract new_version from nested update.' );
	}

	/**
	 * Test format_theme_for_output() with object input.
	 *
	 * @return void
	 */
	public function test_format_theme_handles_object_input() {
		$theme = (object) [
			'slug'    => 'divi',
			'Name'    => 'Divi',
			'Version' => '4.23.0',
			'active'  => 1,
		];

		$result = MainWP_Abilities_Util::format_theme_for_output( $theme );

		$this->assertIsArray( $result, 'Result should be an array.' );
		$this->assertEquals( 'divi', $result['slug'], 'Slug should match.' );
		$this->assertEquals( 'Divi', $result['name'], 'Name should match.' );
	}

	/**
	 * Test format_theme_for_output() with non-array/non-object input.
	 *
	 * @return void
	 */
	public function test_format_theme_handles_invalid_input() {
		// Test null input.
		$result1 = MainWP_Abilities_Util::format_theme_for_output( null );
		$this->assertIsArray( $result1, 'Null input should return array.' );
		$this->assertArrayHasKey( 'slug', $result1, 'Should have slug key.' );

		// Test string input.
		$result2 = MainWP_Abilities_Util::format_theme_for_output( 'not-an-array' );
		$this->assertIsArray( $result2, 'String input should return array.' );
	}

	/**
	 * Test format_theme_for_output() always returns expected keys.
	 *
	 * @return void
	 */
	public function test_format_theme_always_returns_expected_keys() {
		$expected_keys = [ 'slug', 'name', 'version', 'active', 'has_update', 'update_version' ];

		// Test with empty array.
		$result = MainWP_Abilities_Util::format_theme_for_output( [] );

		foreach ( $expected_keys as $key ) {
			$this->assertArrayHasKey( $key, $result, "Result should always have '{$key}' key." );
		}
	}

	/**
	 * Test format_theme_for_output() sanitizes output.
	 *
	 * @return void
	 */
	public function test_format_theme_sanitizes_output() {
		$theme = [
			'slug'    => '<script>alert("xss")</script>',
			'Name'    => '<img src=x onerror=alert("xss")>',
			'Version' => '1.0<script>',
		];

		$result = MainWP_Abilities_Util::format_theme_for_output( $theme );

		$this->assertStringNotContainsString( '<script>', $result['slug'], 'Slug should be sanitized.' );
		$this->assertStringNotContainsString( '<img', $result['name'], 'Name should be sanitized.' );
		$this->assertStringNotContainsString( '<script>', $result['version'], 'Version should be sanitized.' );
	}

	// =========================================================================
	// Edge Cases & Consistency Tests
	// =========================================================================

	/**
	 * Test that plugin and theme formatters produce consistent output structure.
	 *
	 * @return void
	 */
	public function test_formatters_produce_consistent_structure() {
		$plugin_result = MainWP_Abilities_Util::format_plugin_for_output( [] );
		$theme_result  = MainWP_Abilities_Util::format_theme_for_output( [] );

		// Both should have same keys.
		$this->assertEquals(
			array_keys( $plugin_result ),
			array_keys( $theme_result ),
			'Plugin and theme formatters should return same keys.'
		);
	}

	/**
	 * Test capitalized key takes precedence over lowercase when both present.
	 *
	 * @return void
	 */
	public function test_capitalized_key_takes_precedence() {
		$plugin = [
			'slug'    => 'test-plugin',
			'Name'    => 'Capitalized Name',
			'name'    => 'lowercase name',
			'Version' => '2.0.0',
			'version' => '1.0.0',
		];

		$result = MainWP_Abilities_Util::format_plugin_for_output( $plugin );

		$this->assertEquals( 'Capitalized Name', $result['name'], 'Capitalized Name should take precedence.' );
		$this->assertEquals( '2.0.0', $result['version'], 'Capitalized Version should take precedence.' );
	}
}
