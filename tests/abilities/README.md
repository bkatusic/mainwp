# MainWP Abilities API Test Suite

Comprehensive test coverage for the MainWP Abilities API integration (Phase 4 of the integration plan).

## Prerequisites

- WordPress test harness installed via `bin/install-wp-tests.sh`
- Abilities API plugin installed and activated (or mock functions available)
- PHPUnit configured with `WP_TESTS_DIR` environment variable

## Running Tests

```bash
# Run all abilities tests
WP_TESTS_DIR=/path/to/wordpress-tests-lib phpunit --bootstrap tests/bootstrap.php tests/abilities/

# Run specific test file
WP_TESTS_DIR=/path/to/wordpress-tests-lib phpunit --bootstrap tests/bootstrap.php tests/abilities/test-list-sites-ability.php

# Run with verbose output
WP_TESTS_DIR=/path/to/wordpress-tests-lib phpunit --bootstrap tests/bootstrap.php tests/abilities/ --verbose

# Run with coverage (requires Xdebug)
WP_TESTS_DIR=/path/to/wordpress-tests-lib phpunit --bootstrap tests/bootstrap.php --coverage-html coverage/ tests/abilities/
```

## Test Files

### Base Classes

| File | Description |
|------|-------------|
| `class-mainwp-abilities-test-case.php` | Base test case with fixtures and helpers for ability tests |

### Discovery & Registration

| File | Description |
|------|-------------|
| `test-abilities-discovery.php` | Ability/category registration and discoverability |

### Sites Abilities

| File | Description |
|------|-------------|
| `test-list-sites-ability.php` | `mainwp/list-sites-v1` - List sites with pagination, filtering, search |
| `test-get-site-ability.php` | `mainwp/get-site-v1` - Single site retrieval by ID/domain |
| `test-sync-sites-ability.php` | `mainwp/sync-sites-v1` - Site sync with batch queuing |
| `test-site-plugins-themes-abilities.php` | `mainwp/get-site-plugins-v1` and `mainwp/get-site-themes-v1` |

### Updates Abilities

| File | Description |
|------|-------------|
| `test-updates-abilities.php` | All update abilities: list, run, ignored updates |

### Cross-Cutting Concerns

| File | Description |
|------|-------------|
| `test-permissions.php` | Permission callbacks, ACL enforcement, REST auth |
| `test-batch-operations.php` | Queuing, thresholds, job storage, cron scheduling |
| `test-rest-integration.php` | REST v2 abilities-first pattern and fallback |

## Test Coverage

### Abilities Tested (9 total)

**Sites Category (5 abilities):**
- `mainwp/list-sites-v1`
- `mainwp/get-site-v1`
- `mainwp/sync-sites-v1`
- `mainwp/get-site-plugins-v1`
- `mainwp/get-site-themes-v1`

**Updates Category (4 abilities):**
- `mainwp/list-updates-v1`
- `mainwp/run-updates-v1`
- `mainwp/list-ignored-updates-v1`
- `mainwp/set-ignored-updates-v1`

### Test Areas

| Area | Coverage |
|------|----------|
| Registration | Categories and abilities are discoverable |
| Schemas | Input/output validation |
| Execute callbacks | Core functionality for each ability |
| Permissions | Global (manage_options), per-site ACLs, REST API keys |
| Batch operations | Queuing for >50 sites, immediate execution for ≤50 |
| Error handling | Offline sites, child version checks, not found, partial failures |
| REST integration | Abilities-first pattern, parameter mapping, fallback |

## Test Patterns

### Skipping Tests Without Abilities API

```php
$this->skip_if_no_abilities_api();
```

### Creating Test Sites

```php
$site_id = $this->create_test_site([
    'name'                 => 'Test Site',
    'url'                  => 'https://test.example.com/',
    'offline_check_result' => 1, // 1 = online, -1 = offline
    'version'              => '5.0.0', // Child plugin version
]);
```

### Setting Up Admin User

```php
$this->set_current_user_as_admin();
```

### Executing Abilities

```php
$result = $this->execute_ability('mainwp/list-sites-v1', [
    'page'     => 1,
    'per_page' => 10,
]);
```

### Mocking Failures

```php
// Mock sync failure
$this->mock_sync_failure('mainwp_connection_failed', 'Connection timed out');

// Mock partial update result
$this->mock_partial_update_result(
    [$success_site_id],
    [['site_id' => $fail_site_id, 'code' => 'error_code', 'message' => 'Error message']]
);
```

## Key Test Scenarios

### Batch Queuing Threshold

- **≤50 sites**: Execute immediately, return `synced`/`errors` arrays
- **>50 sites**: Queue for background processing, return `job_id`/`status_url`

### Permission Checks

1. **Global permission**: Requires `manage_options` capability
2. **Per-site ACL**: Uses `MainWP_System::check_site_access()`
3. **REST API key**: Prioritizes `MainWP_REST_Authentication::get_rest_valid_user()`

### Site Resolution

- Numeric identifiers → Resolve as MainWP site ID
- String identifiers → Normalize URL and search by domain

### Error Handling

- Offline sites → `mainwp_site_offline` error code
- Outdated child → `mainwp_child_outdated` error code
- Not found → `mainwp_site_not_found` error code
- Access denied → `mainwp_access_denied` error code

## Notes

- Tests automatically clean up created sites in `tearDown()`
- All tests skip gracefully if Abilities API is not available
- Batch queuing tests create many sites (50-60) and may be slow
- REST integration tests require REST API infrastructure
- Some tests may need actual plugin/theme data seeded for full coverage

## Integration Plan Reference

These tests implement the testing strategy from:
`.mwpdev/plans/abilities-api/abilities-api-integration-plan.md` (Section 11, lines 2333-2934)

## Contributing

When adding new tests:

1. Extend `MainWP_Abilities_Test_Case` for ability tests
2. Extend `WP_Test_REST_TestCase` for REST-specific tests
3. Use `skip_if_no_abilities_api()` at the start of each test
4. Clean up test data in `tearDown()`
5. Follow WordPress coding standards (4-space indentation)
