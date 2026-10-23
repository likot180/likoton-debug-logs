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
			'nonce'       => wp_create_nonce( 'likoton_debug_logs_load_more_logs' ),
			'deleteNonce' => wp_create_nonce( 'likoton_debug_logs_delete_selected' ),
			'exportNonce' => wp_create_nonce( 'likoton_debug_logs_export_logs' ),
			'exportUrl'   => admin_url( 'admin-post.php' ),
			'locale'      => str_replace( '_', '-', get_user_locale() ),
			'i18n'        => [
				'today'         => __( 'Today', 'likoton-debug-logs' ),
				'clear'         => __( 'Clear', 'likoton-debug-logs' ),
				/* translators: %d: number of logs shown in the table */
				'logsCount'     => __( 'Logs: %d', 'likoton-debug-logs' ),
				/* translators: 1: number of selected logs, 2: number of logs shown in the table */
				'selectedCount' => __( '%1$d of %2$d selected', 'likoton-debug-logs' ),
				'copied'        => __( 'Copied to clipboard', 'likoton-debug-logs' ),
				'copyFailed'    => __( 'Could not copy to clipboard.', 'likoton-debug-logs' ),
				'deleteFailed'  => __( 'Could not delete the selected logs.', 'likoton-debug-logs' ),
				'deleteAllTitle'   => __( 'Delete all logs', 'likoton-debug-logs' ),
				'deleteAllConfirm' => __( 'Delete all logs? This cannot be undone.', 'likoton-debug-logs' ),
			],
		] );
    }
}
