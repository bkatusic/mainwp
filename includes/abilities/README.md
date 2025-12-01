# MainWP Abilities API Integration

This directory contains MainWP's integration with the WordPress Abilities API.

## Overview

The Abilities API provides a standardized way to expose WordPress operations as discoverable,
schema-validated capabilities. MainWP uses this to expose site management and update operations
that can be consumed by AI agents, automation tools, and third-party integrations.

**Feature-gated:** All code in this directory is designed to do nothing if the Abilities API
plugin is not installed. This ensures backward compatibility with existing deployments.

## Files

| File | Purpose |
|------|---------|
| `class-mainwp-abilities.php` | Bootstrap and registration. Initializes the integration and registers ability categories. |
| `class-mainwp-abilities-sites.php` | Site-related abilities: list sites, get site details, sync sites, check connectivity. |
| `class-mainwp-abilities-updates.php` | Update abilities: list updates, run updates, manage ignored updates. |
| `class-mainwp-abilities-util.php` | Shared utilities: permission checks, input validation, site access control. |

## Feature Detection

To check if the Abilities API is available:

```php
if ( function_exists( 'wp_register_ability' ) ) {
    // Abilities API is available
}
```

## Authentication

MainWP abilities authenticate via the existing REST API key system:
- Consumer key/secret authentication works for ability endpoints
- Session-based authentication (logged-in WordPress users) also works
- All abilities require `manage_options` capability

## Ability Categories

- `mainwp-sites` - Site management abilities
- `mainwp-updates` - Update management abilities

## Documentation

For full Abilities API documentation, see:
- `.mwpdev/docs/abilities-api-docs/` (local canonical source)
- Integration plan: `.mwpdev/plans/abilities-api/abilities-api-integration-plan.md`

## Testing

```bash
# Run abilities tests
WP_TESTS_DIR=/tmp/wordpress-tests-lib ./vendor/bin/phpunit --testsuite=Abilities
```

See `tests/abilities/` for comprehensive test coverage including:
- Ability registration and discovery
- Permission enforcement
- Input/output schema validation
- REST API integration
