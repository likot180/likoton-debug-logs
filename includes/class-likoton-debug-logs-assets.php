<?php

namespace Likoton\DebugLogs;

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

class Likoton_Debug_Logs_Assets {

    public static function init() {
        add_action( 'admin_enqueue_scripts', [ __CLASS__, 'enqueue' ] );
    }

    public static function enqueue( $hook ) {
        if ( strpos( $hook, 'likoton-debug-logs-' ) === false ) {
            return;
        }

		// CSS (light)
		wp_enqueue_style(
			'likoton-debug-logs-admin',
			LIKOTON_DEBUG_LOGS_PLUGIN_URL . 'assets/admin.css',
			[],
			filemtime( LIKOTON_DEBUG_LOGS_PLUGIN_DIR . 'assets/admin.css' )
		);

		// CSS (dark)
		if ( get_option( 'likoton_debug_logs_dark_mode', 0 ) ) {
			wp_enqueue_style(
				'likoton-debug-logs-admin-dark',
				LIKOTON_DEBUG_LOGS_PLUGIN_URL . 'assets/admin-dark.css',
				[ 'likoton-debug-logs-admin' ],
				filemtime( LIKOTON_DEBUG_LOGS_PLUGIN_DIR . 'assets/admin-dark.css' )
			);
		}

		// JS
		wp_enqueue_script(
			'likoton-debug-logs-admin',
			LIKOTON_DEBUG_LOGS_PLUGIN_URL . 'assets/admin.js',
			[ 'jquery' ],
			filemtime( LIKOTON_DEBUG_LOGS_PLUGIN_DIR . 'assets/admin.js' ),
			true
		);

		wp_localize_script( 'likoton-debug-logs-admin', 'likotonDebugLogsData', [
			'nonce' => wp_create_nonce( 'likoton_debug_logs_load_more_logs' ),
		] );
    }
}
