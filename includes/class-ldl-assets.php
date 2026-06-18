<?php

namespace Likoton\DebugLogs;

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

class LDL_Assets {

    public static function init() {
        add_action( 'admin_enqueue_scripts', [ __CLASS__, 'enqueue' ] );
    }

    public static function enqueue( $hook ) {
        if ( strpos( $hook, 'ldl-' ) === false ) {
            return;
        }

		// CSS (light)
		wp_enqueue_style(
			'ldl-admin',
			LDL_PLUGIN_URL . 'assets/admin.css',
			[],
			filemtime( LDL_PLUGIN_PATH . 'assets/admin.css' )
		);

		// CSS (dark)
		if ( get_option( 'ldl_dark_mode', 0 ) ) {
			wp_enqueue_style(
				'ldl-admin-dark',
				LDL_PLUGIN_URL . 'assets/admin-dark.css',
				[ 'ldl-admin' ],
				filemtime( LDL_PLUGIN_PATH . 'assets/admin-dark.css' )
			);
		}

		// JS
		wp_enqueue_script(
			'ldl-admin',
			LDL_PLUGIN_URL . 'assets/admin.js',
			[ 'jquery' ],
			filemtime( LDL_PLUGIN_PATH . 'assets/admin.js' ),
			true
		);
    }
}
