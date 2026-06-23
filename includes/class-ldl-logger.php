<?php

namespace Likoton\DebugLogs;

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

class LDL_Logger {

    private static $psr_levels = [
        'emergency',
        'alert',
        'critical',
        'error',
        'warning',
        'notice',
        'info',
        'debug',
    ];

    public static function init() {
        add_action( 'wp_error_added', [ __CLASS__, 'log_wp_error' ], 10, 4 );
		// phpcs:ignore WordPress.PHP.DevelopmentFunctions.error_log_set_error_handler
        set_error_handler( [ __CLASS__, 'log_php_error' ] );
        add_action( 'rest_request_after_callbacks', [ __CLASS__, 'log_rest_request' ], 10, 3 );
        add_action( 'wp_login', [ __CLASS__, 'log_user_login' ], 10, 2 );
    }

    protected static function insert_log( $level, $source, $message, $context = null ) {
        global $wpdb;
        
        $level = strtolower( $level );

        $enabled = get_option( 'ldl_enabled_levels', [] );

        if ( ! in_array( $level, $enabled, true ) ) {
            return;
        }

        if ( ! in_array( $level, self::$psr_levels, true ) ) {
            if ( ! preg_match( '/^(user_|core_|compile_|recoverable_|php|deprecated|strict|parse)/', $level ) ) {
                $level = 'info';
            }
        }

        $table = $wpdb->prefix . LDL_Installer::TABLE_NAME;

        $ip      = isset( $_SERVER['REMOTE_ADDR'] ) ? sanitize_text_field( wp_unslash( $_SERVER['REMOTE_ADDR'] ) ) : null;
        $user_id = get_current_user_id() ?: null;

        // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery
        $wpdb->insert(
            $table,
            [
                'level'      => sanitize_text_field( $level ),
                'source'     => sanitize_text_field( $source ),
                'message'    => wp_kses_post( $message ),
                'context'    => maybe_serialize( $context ),
                'ip'         => $ip,
                'user_id'    => $user_id,
                'created_at' => current_time( 'mysql', true ),
            ],
            [ '%s', '%s', '%s', '%s', '%s', '%d', '%s' ]
        );
    }

    public static function log_wp_error( $code, $message, $data, $wp_error ) {
        $msg = sprintf( '%s: %s', $code, $message );
        self::insert_log( 'wp_error', 'wordpress', $msg, $data );
    }

    public static function log_php_error( $errno, $errstr, $errfile, $errline ) {
        $levels = [
            E_ERROR             => 'error',
            E_WARNING           => 'warning',
            E_PARSE             => 'parse',
            E_NOTICE            => 'notice',
            E_CORE_ERROR        => 'core_error',
            E_CORE_WARNING      => 'core_warning',
            E_COMPILE_ERROR     => 'compile_error',
            E_COMPILE_WARNING   => 'compile_warning',
            E_USER_ERROR        => 'user_error',
            E_USER_WARNING      => 'user_warning',
            E_USER_NOTICE       => 'user_notice',
            E_STRICT            => 'strict',
            E_RECOVERABLE_ERROR => 'recoverable_error',
            E_DEPRECATED        => 'deprecated',
            E_USER_DEPRECATED   => 'user_deprecated',
        ];

        $level = isset( $levels[ $errno ] ) ? $levels[ $errno ] : 'php';

        $msg = sprintf(
            '%s in %s:%d',
            $errstr,
            $errfile,
            $errline
        );

        self::insert_log( $level, 'php', $msg );
    }

    public static function log_rest_request( $response, $handler, $request ) {
        $route  = $request->get_route();
        $method = $request->get_method();

        $context = [
            'route'  => $route,
            'method' => $method,
            'params' => $request->get_params(),
        ];

        $msg = sprintf( 'REST %s %s', $method, $route );
        self::insert_log( 'info', 'rest_api', $msg, $context );

        return $response;
    }

    public static function log_user_login( $user_login, $user ) {
        $msg = sprintf( 'User "%s" (ID %d) logged in.', $user_login, $user->ID );
        self::insert_log( 'info', 'login', $msg, [ 'user_id' => $user->ID ] );
    }

    public static function log( $level, $source, $message, $context = null ) {
        self::insert_log( $level, $source, $message, $context );
    }
}
