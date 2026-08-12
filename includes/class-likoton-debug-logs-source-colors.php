<?php

namespace Likoton\DebugLogs;

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

/**
 * Manages custom colors for log sources.
 *
 * Colors are stored in option likoton_debug_logs_source_colors as an array
 * keyed by source slug (e.g. ['rest_api' => '#6f42c1']).
 *
 * REST API (requires authentication + manage_options capability):
 *
 *   GET  /wp-json/likoton-debug-logs/v1/source-colors
 *        → { "php": "#20c997", "rest_api": "#6f42c1", ... }
 *
 *   PUT  /wp-json/likoton-debug-logs/v1/source-colors
 *        body: { "rest_api": "#ff5733", "my_plugin": "#aabbcc" }
 *        → { "updated": { "rest_api": "#ff5733", "my_plugin": "#aabbcc" } }
 *
 *   DELETE /wp-json/likoton-debug-logs/v1/source-colors/{source}
 *        → { "deleted": "my_plugin" }
 *        (restores the source to its default color or removes custom override)
 */
class Likoton_Debug_Logs_Source_Colors {

    const OPTION = 'likoton_debug_logs_source_colors';

    public static function init() {
        add_action( 'rest_api_init', [ __CLASS__, 'register_routes' ] );
        add_action( 'admin_head',    [ __CLASS__, 'inject_inline_styles' ] );
    }

    // ---------------------------------------------------------------
    // Helpers
    // ---------------------------------------------------------------

    /** Returns merged array: defaults overridden by saved custom colors */
    public static function get_all_colors() {
        $defaults = Likoton_Debug_Logs_Installer::get_default_source_colors();
        $custom   = get_option( self::OPTION, [] );
        return array_merge( $defaults, $custom );
    }

    /** Returns only the custom (user-saved) overrides */
    public static function get_custom_colors() {
        return get_option( self::OPTION, [] );
    }

    private static function is_valid_hex( $color ) {
        return (bool) preg_match( '/^#([0-9a-fA-F]{3}|[0-9a-fA-F]{6})$/', $color );
    }

    private static function sanitize_source( $source ) {
        return sanitize_key( $source );
    }

    // ---------------------------------------------------------------
    // Inline CSS injection
    // ---------------------------------------------------------------

    public static function inject_inline_styles() {
        $screen = get_current_screen();
        if ( ! $screen || strpos( $screen->id, 'likoton-debug-logs' ) === false ) {
            return;
        }

        $colors = self::get_all_colors();
        if ( empty( $colors ) ) {
            return;
        }

        echo "<style id=\"likoton-debug-logs-source-colors\">\n";
        foreach ( $colors as $source => $color ) {
            $source = sanitize_key( $source );
            $color  = sanitize_hex_color( $color );
            if ( ! $source || ! $color ) {
                continue;
            }
            // Text color: white for dark backgrounds, black for light ones.
            $text = self::is_dark( $color ) ? '#fff' : '#000';
            printf(
                ".ldl-source-%s { background: %s !important; color: %s !important; }\n",
                esc_attr( $source ),
                esc_attr( $color ),
                esc_attr( $text )
            );
        }
        echo "</style>\n";
    }

    /** Simple luminance check to decide white vs black text */
    private static function is_dark( $hex ) {
        $hex = ltrim( $hex, '#' );
        if ( strlen( $hex ) === 3 ) {
            $hex = $hex[0] . $hex[0] . $hex[1] . $hex[1] . $hex[2] . $hex[2];
        }
        $r = hexdec( substr( $hex, 0, 2 ) );
        $g = hexdec( substr( $hex, 2, 2 ) );
        $b = hexdec( substr( $hex, 4, 2 ) );
        // Perceived luminance (ITU-R BT.709)
        $luminance = ( 0.2126 * $r + 0.7152 * $g + 0.0722 * $b ) / 255;
        return $luminance < 0.5;
    }

    // ---------------------------------------------------------------
    // REST routes
    // ---------------------------------------------------------------

    public static function register_routes() {
        $namespace = 'likoton-debug-logs/v1';

        register_rest_route( $namespace, '/source-colors', [
            [
                'methods'             => 'GET',
                'callback'            => [ __CLASS__, 'rest_get' ],
                'permission_callback' => [ __CLASS__, 'rest_permission' ],
            ],
            [
                'methods'             => 'PUT',
                'callback'            => [ __CLASS__, 'rest_put' ],
                'permission_callback' => [ __CLASS__, 'rest_permission' ],
                'args'                => [
                    // No fixed schema — accepts any source => hex pairs
                ],
            ],
        ] );

        register_rest_route( $namespace, '/source-colors/(?P<source>[a-z0-9_-]+)', [
            [
                'methods'             => 'DELETE',
                'callback'            => [ __CLASS__, 'rest_delete' ],
                'permission_callback' => [ __CLASS__, 'rest_permission' ],
            ],
        ] );
    }

    public static function rest_permission() {
        return current_user_can( get_option( 'likoton_debug_logs_capability', 'manage_options' ) );
    }

    /** GET /source-colors → merged defaults + custom */
    public static function rest_get( \WP_REST_Request $request ) {
        return rest_ensure_response( self::get_all_colors() );
    }

    /** PUT /source-colors  body: { "source": "#rrggbb", ... } */
    public static function rest_put( \WP_REST_Request $request ) {
        $body = $request->get_json_params();

        if ( ! is_array( $body ) || empty( $body ) ) {
            return new \WP_Error(
                'invalid_body',
                'Body must be a JSON object of source => hex pairs.',
                [ 'status' => 400 ]
            );
        }

        $custom  = self::get_custom_colors();
        $updated = [];
        $errors  = [];

        foreach ( $body as $source => $color ) {
            $source = self::sanitize_source( $source );
            $color  = sanitize_hex_color( $color );

            if ( ! $source ) {
                $errors[] = "Invalid source key.";
                continue;
            }
            if ( ! $color ) {
                $errors[] = "Invalid hex color for source '$source'. Use #rrggbb or #rgb.";
                continue;
            }

            $custom[ $source ] = $color;
            $updated[ $source ] = $color;
        }

        if ( ! empty( $updated ) ) {
            update_option( self::OPTION, $custom );
        }

        $response = [ 'updated' => $updated ];
        if ( ! empty( $errors ) ) {
            $response['errors'] = $errors;
        }

        return rest_ensure_response( $response );
    }

    /** DELETE /source-colors/{source} → removes custom override */
    public static function rest_delete( \WP_REST_Request $request ) {
        $source = self::sanitize_source( $request->get_param( 'source' ) );
        $custom = self::get_custom_colors();

        if ( ! isset( $custom[ $source ] ) ) {
            return new \WP_Error(
                'not_found',
                "No custom color found for source '$source'.",
                [ 'status' => 404 ]
            );
        }

        unset( $custom[ $source ] );
        update_option( self::OPTION, $custom );

        return rest_ensure_response( [ 'deleted' => $source ] );
    }
}
