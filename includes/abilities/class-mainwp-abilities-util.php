<?php
/**
 * MainWP Abilities Utilities
 *
 * @package MainWP\Dashboard
 */

namespace MainWP\Dashboard;

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

/**
 * Class MainWP_Abilities_Util
 *
 * Shared utility functions for MainWP abilities.
 */
class MainWP_Abilities_Util {

    /**
     * Check if current request has REST API permission.
     *
     * Prioritizes REST API key authentication; falls back to session-based
     * authentication for legacy/admin use. This ensures compatibility with
     * MainWP's consumer key/secret authentication which doesn't require a session.
     *
     * Note: Invalid or missing API key errors (e.g., 'mainwp_rest_authentication_error')
     * are handled earlier in the REST authentication stack by MainWP_REST_Authentication
     * via the 'rest_authentication_errors' filter. By the time this permission callback
     * runs, invalid API requests have already been rejected. We only need to check if
     * a valid REST user exists and verify their capabilities.
     *
     * IMPORTANT: Returns true on success or WP_Error on failure.
     * This is required for abilities to surface proper error codes to consumers.
     *
     * @param mixed $input The ability input (unused but required by signature).
     * @return true|\WP_Error True on success, WP_Error with code on failure.
     */
    public static function check_rest_api_permission( $input = null ) {
        // Prioritize MainWP REST API key authentication.
        // Note: Key-specific errors (invalid key, invalid secret, bad signature) are
        // handled by MainWP_REST_Authentication::check_authentication_error() which
        // hooks into 'rest_authentication_errors' and blocks requests before this runs.
        if ( class_exists( '\MainWP_REST_Authentication' ) ) {
            $auth      = \MainWP_REST_Authentication::get_instance();
            $rest_user = $auth->get_rest_valid_user();

            // If REST auth has validated a user, trust that authentication.
            if ( ! empty( $rest_user ) ) {
                // REST API key is valid, check capability of the associated user.
                if ( ! current_user_can( 'manage_options' ) ) {
                    return new \WP_Error(
                        'mainwp_permission_denied',
                        __( 'API key user does not have sufficient permissions.', 'mainwp' ),
                        [ 'status' => 403 ]
                    );
                }
                return true;
            }
        }

        // Fallback: session-based authentication for admin/legacy use.
        if ( ! is_user_logged_in() ) {
            return new \WP_Error(
                'mainwp_permission_denied',
                __( 'You must be logged in to access this ability.', 'mainwp' ),
                [ 'status' => 401 ]
            );
        }

        // Check for manage_options capability.
        if ( ! current_user_can( 'manage_options' ) ) {
            return new \WP_Error(
                'mainwp_permission_denied',
                __( 'You do not have permission to perform this action.', 'mainwp' ),
                [ 'status' => 403 ]
            );
        }

        return true;
    }

    /**
     * Check if current user can manage sites (write operations).
     *
     * Currently a wrapper over check_rest_api_permission() which already verifies
     * manage_options capability. This method exists as an extension point for future
     * granular permissions (e.g., a dedicated 'mainwp_manage_sites' capability).
     *
     * @param mixed $input The ability input (unused but required by signature).
     * @return true|\WP_Error True on success, WP_Error on failure.
     */
    public static function check_manage_sites_permission( $input = null ) {
        $base = self::check_rest_api_permission( $input );
        if ( is_wp_error( $base ) ) {
            return $base;
        }

        // Future: Add granular 'mainwp_manage_sites' capability check here.
        // For now, manage_options is already verified in check_rest_api_permission().

        return true;
    }

    /**
     * Check if current user can access a specific site.
     *
     * This enforces per-site ACLs beyond the basic REST API permission check.
     * Use this for all site-specific operations (get, sync, update, plugins, themes).
     *
     * IMPORTANT: Returns true on success or WP_Error on failure.
     * For execute callbacks that need a boolean check, use the helper method below.
     *
     * @param int|object $site Site ID or site object.
     * @param mixed      $input The ability input (unused but required by signature).
     * @return true|\WP_Error True on success, WP_Error on failure.
     */
    public static function check_site_access( $site, $input = null ) {
        // First, verify basic REST API permission.
        $base_check = self::check_rest_api_permission( $input );
        if ( is_wp_error( $base_check ) ) {
            return $base_check;
        }

        $site_id = is_object( $site ) ? (int) $site->id : (int) $site;

        // Use MainWP's existing per-site access control.
        if ( class_exists( 'MainWP_System' ) ) {
            $system = MainWP_System::instance();
            if ( method_exists( $system, 'check_site_access' ) ) {
                if ( ! $system->check_site_access( $site_id ) ) {
                    return new \WP_Error(
                        'mainwp_access_denied',
                        __( 'You do not have permission to access this site.', 'mainwp' ),
                        [ 'status' => 403 ]
                    );
                }
            }
        }

        return true;
    }

    /**
     * Check site access and return boolean.
     *
     * Convenience method for execute callbacks that need bool-style checks.
     * Use check_site_access() for permission_callback (returns WP_Error for proper error responses).
     *
     * @param int|object $site Site ID or site object.
     * @param mixed      $input The ability input.
     * @return bool True if access allowed, false otherwise.
     */
    public static function can_access_site( $site, $input = null ): bool {
        return true === self::check_site_access( $site, $input );
    }

    /**
     * Check if site's child plugin meets minimum version requirement.
     *
     * Use this in execute callbacks before performing operations that require
     * child plugin communication. Returns WP_Error with 'mainwp_child_outdated'
     * code if the child version is too low.
     *
     * @param object $site        Site object with version property.
     * @param string $min_version Minimum required version (default: '4.0.0').
     * @return true|\WP_Error True if version OK, WP_Error if outdated.
     */
    public static function check_child_version( $site, string $min_version = '4.0.0' ) {
        // Validate site parameter.
        if ( ! is_object( $site ) || ! isset( $site->id ) ) {
            return new \WP_Error(
                'mainwp_internal_error',
                __( 'Invalid site object provided.', 'mainwp' ),
                [ 'status' => 500 ]
            );
        }

        $child_version = $site->version ?? '0.0.0';
        if ( version_compare( $child_version, $min_version, '<' ) ) {
            return new \WP_Error(
                'mainwp_child_outdated',
                sprintf(
                    /* translators: %s: minimum version */
                    __( 'Child plugin version %s or higher required.', 'mainwp' ),
                    $min_version
                ),
                [ 'status' => 400, 'site_id' => (int) $site->id ]
            );
        }
        return true;
    }

    /**
     * Check site access for batch operations.
     *
     * Filters a list of site identifiers to only those the user can access.
     * Returns both accessible sites and access-denied errors.
     *
     * @param array $site_ids_or_domains Array of site IDs or URLs/domains.
     * @param mixed $input               The ability input (unused but required by signature).
     * @return array Array with 'allowed' (accessible sites) and 'denied' (access errors).
     */
    public static function check_batch_site_access( array $site_ids_or_domains, $input = null ): array {
        $allowed = [];
        $denied  = [];

        foreach ( $site_ids_or_domains as $identifier ) {
            $site = self::resolve_site( $identifier );

            if ( is_wp_error( $site ) ) {
                $error_data = $site->get_error_data();
                $denied[]   = [
                    'identifier' => $identifier,
                    'code'       => $site->get_error_code(),
                    'message'    => $site->get_error_message(),
                    'status'     => isset( $error_data['status'] ) ? $error_data['status'] : null,
                ];
                continue;
            }

            $access_check = self::check_site_access( $site, $input );
            if ( is_wp_error( $access_check ) ) {
                $error_data = $access_check->get_error_data();
                $denied[]   = [
                    'identifier' => $identifier,
                    'code'       => $access_check->get_error_code(),
                    'message'    => $access_check->get_error_message(),
                    'status'     => isset( $error_data['status'] ) ? $error_data['status'] : null,
                ];
                continue;
            }

            $allowed[] = $site;
        }

        return [
            'allowed' => $allowed,
            'denied'  => $denied,
        ];
    }

    /**
     * Resolve a site identifier to a MainWP site object.
     *
     * Resolution order:
     * 1. If numeric → treat as MainWP site ID
     * 2. Otherwise → treat as URL/domain and resolve
     *
     * @param int|string $site_id_or_domain Site ID or URL/domain.
     * @return object|\WP_Error Site object on success, WP_Error on failure.
     */
    public static function resolve_site( $site_id_or_domain ) {
        if ( ! class_exists( 'MainWP_DB' ) ) {
            return new \WP_Error(
                'mainwp_internal_error',
                __( 'MainWP database class is not available.', 'mainwp' ),
                [ 'status' => 500 ]
            );
        }

        $db = MainWP_DB::instance();

        // Numeric → try as site ID.
        if ( is_numeric( $site_id_or_domain ) ) {
            $site = $db->get_website_by_id( (int) $site_id_or_domain );
            if ( $site ) {
                return $site;
            }
            return new \WP_Error(
                'mainwp_site_not_found',
                sprintf(
                    /* translators: %d: site ID */
                    __( 'No site found with ID %d.', 'mainwp' ),
                    (int) $site_id_or_domain
                ),
                [ 'status' => 404 ]
            );
        }

        // Non-numeric → treat as URL/domain.
        $url  = self::normalize_url( $site_id_or_domain );
        $site = $db->get_websites_by_url( $url );

        if ( $site && ! empty( $site ) ) {
            return is_array( $site ) ? $site[0] : $site;
        }

        return new \WP_Error(
            'mainwp_site_not_found',
            sprintf(
                /* translators: %s: domain or URL */
                __( 'No site found matching "%s".', 'mainwp' ),
                $site_id_or_domain
            ),
            [ 'status' => 404 ]
        );
    }

    /**
     * Resolve multiple site identifiers.
     *
     * @param array $site_ids_or_domains Array of site IDs or URLs/domains.
     * @return array Array with 'sites' (resolved) and 'errors' (failed).
     */
    public static function resolve_sites( array $site_ids_or_domains ): array {
        $sites  = [];
        $errors = [];

        foreach ( $site_ids_or_domains as $identifier ) {
            $site = self::resolve_site( $identifier );
            if ( is_wp_error( $site ) ) {
                $error_data = $site->get_error_data();
                $errors[]   = [
                    'identifier' => $identifier,
                    'code'       => $site->get_error_code(),
                    'message'    => $site->get_error_message(),
                    'status'     => isset( $error_data['status'] ) ? $error_data['status'] : null,
                ];
            } else {
                $sites[] = $site;
            }
        }

        return [
            'sites'  => $sites,
            'errors' => $errors,
        ];
    }

    /**
     * Normalize a URL for site lookup.
     *
     * IMPORTANT: This normalization is for site resolution only.
     *
     * Handles:
     * - Protocol stripping (https://, http://)
     * - www prefix removal
     * - Trailing slash enforcement
     *
     * LIMITATIONS (prefer site ID for these cases):
     * - Port numbers (example.com:8080) - not stripped, may fail to match
     * - Subdirectory multisites (example.com/site1 vs example.com/site2) - may collide
     * - URL-encoded characters - not decoded
     * - IDN/punycode domains - not normalized
     *
     * RECOMMENDATION: For ambiguous cases or programmatic access, always use
     * site ID instead of URL/domain.
     *
     * @param string $url URL to normalize.
     * @return string Normalized URL.
     */
    public static function normalize_url( string $url ): string {
        // Remove protocol and www prefix.
        $url = preg_replace( '#^https?://(www\.)?#i', '', $url );

        // Ensure trailing slash.
        $url = trailingslashit( $url );

        return $url;
    }

    /**
     * Create a permission callback for site-specific abilities.
     *
     * The Abilities API passes $input to the permission_callback, but our
     * check_site_access() method expects a resolved site object. This wrapper
     * resolves the site from input first, then performs the access check.
     *
     * IMPORTANT: Use this for any ability that operates on a single site
     * identified by 'site_id_or_domain' in the input schema.
     *
     * @param string $input_key The input key containing the site identifier (default: 'site_id_or_domain').
     * @return callable Permission callback closure.
     */
    public static function make_site_permission_callback( string $input_key = 'site_id_or_domain' ): callable {
        return function ( $input ) use ( $input_key ) {
            // First check basic REST API permission.
            $base_check = self::check_rest_api_permission( $input );
            if ( is_wp_error( $base_check ) ) {
                return $base_check;
            }

            // Resolve the site from input.
            $identifier = $input[ $input_key ] ?? null;
            if ( null === $identifier ) {
                return new \WP_Error(
                    'mainwp_invalid_input',
                    __( 'Site identifier is required.', 'mainwp' ),
                    [ 'status' => 400 ]
                );
            }

            $site = self::resolve_site( $identifier );
            if ( is_wp_error( $site ) ) {
                return $site;
            }

            // Check per-site access.
            return self::check_site_access( $site, $input );
        };
    }

    /**
     * Map a site object to the standard output format.
     *
     * @param object $site         Site object from database.
     * @param bool   $full_details Whether to include full site details (default: false).
     * @return array Formatted site data.
     */
    public static function format_site_for_output( $site, bool $full_details = false ): array {
        $output = [
            'id'        => (int) $site->id,
            'url'       => (string) $site->url,
            'name'      => (string) $site->name,
            'status'    => self::get_site_status( $site ),
            'client_id' => isset( $site->client_id ) && $site->client_id > 0
                ? (int) $site->client_id
                : null,
        ];

        if ( $full_details ) {
            // Add extended site details for single-site retrieval.
            $output['admin_username'] = $site->adminname ?? '';

            // Get wp_version from site_info JSON (same as REST controller).
            $site_info  = MainWP_DB::instance()->get_website_option( $site->id, 'site_info' );
            $site_info  = ! empty( $site_info ) ? json_decode( $site_info, true ) : array();
            $output['wp_version']     = isset( $site_info['wpversion'] ) ? $site_info['wpversion'] : '';
            $output['child_version']  = $site->version ?? '';
            $output['last_sync']      = $site->dtsSync ?? null;
            $output['notes']          = $site->note ?? '';
        }

        return $output;
    }

    /**
     * Get site connection status string.
     *
     * @param object $site Site object.
     * @return string Status string: 'connected', 'disconnected', or 'suspended'.
     */
    public static function get_site_status( $site ): string {
        // Check if site is suspended.
        if ( isset( $site->suspended ) && 1 === (int) $site->suspended ) {
            return 'suspended';
        }

        // Check offline status.
        if ( isset( $site->offline_check_result ) && -1 === (int) $site->offline_check_result ) {
            return 'disconnected';
        }

        // Check for sync errors.
        if ( isset( $site->sync_errors ) && ! empty( $site->sync_errors ) ) {
            return 'disconnected';
        }

        return 'connected';
    }
}
