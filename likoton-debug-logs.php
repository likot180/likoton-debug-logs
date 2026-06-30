<?php
/**
 * Plugin Name: LiKoToN Debug Logs
 * Plugin URI: https://likoton.pl
 * Description: Collects and displays WordPress and PHP debug logs with filters, live view and dark mode
 * Author: LiKoToN
 * Version: 1.0.0
 * License: GPLv3
 * License URI: License URI: https://www.gnu.org/licenses/gpl-3.0-standalone.html
 * Text Domain: likoton-debug-logs
 */

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

define( 'LIKOTON_DEBUG_LOGS_VERSION', '1.0.0' );
define( 'LIKOTON_DEBUG_LOGS_PLUGIN_DIR', plugin_dir_path( __FILE__ ) );
define( 'LIKOTON_DEBUG_LOGS_PLUGIN_URL', plugin_dir_url( __FILE__ ) );

require_once LIKOTON_DEBUG_LOGS_PLUGIN_DIR . 'includes/class-likoton-debug-logs-installer.php';
require_once LIKOTON_DEBUG_LOGS_PLUGIN_DIR . 'includes/class-likoton-debug-logs-logger.php';
require_once LIKOTON_DEBUG_LOGS_PLUGIN_DIR . 'includes/class-likoton-debug-logs-admin.php';
require_once LIKOTON_DEBUG_LOGS_PLUGIN_DIR . 'includes/class-likoton-debug-logs-assets.php';

add_action( 'init', function () {
    load_plugin_textdomain(
        'likoton-debug-logs',
        false,
        dirname( plugin_basename( __FILE__ ) ) . '/languages'
    );
});

register_activation_hook( __FILE__, [ '\Likoton\DebugLogs\Likoton_Debug_Logs_Installer', 'activate' ] );
register_deactivation_hook( __FILE__, [ '\Likoton\DebugLogs\Likoton_Debug_Logs_Installer', 'deactivate' ] );
register_uninstall_hook( __FILE__, [ '\Likoton\DebugLogs\Likoton_Debug_Logs_Installer', 'uninstall' ] );

add_action( 'plugins_loaded', function () {
    \Likoton\DebugLogs\Likoton_Debug_Logs_Logger::init();
    \Likoton\DebugLogs\Likoton_Debug_Logs_Admin::init();
    \Likoton\DebugLogs\Likoton_Debug_Logs_Assets::init();
} );

add_filter( 'plugin_action_links_' . plugin_basename( __FILE__ ), function ( $links ) {
    $logs_url     = admin_url( 'admin.php?page=likoton-debug-logs-logs' );
    $settings_url = admin_url( 'admin.php?page=likoton-debug-logs-settings' );

    $links[] = '<a href="' . esc_url( $logs_url ) . '">' . esc_html__( 'Logs', 'likoton-debug-logs' ) . '</a>';
    $links[] = '<a href="' . esc_url( $settings_url ) . '">' . esc_html__( 'Settings', 'likoton-debug-logs' ) . '</a>';

    return $links;
} );
