<?php
/**
 * Plugin Name: LiKoToN Debug Logs
 * Plugin URI: https://likoton.pl
 * Description: Collects and displays WordPress and PHP debug logs with filters, live view and dark mode
 * Author: LiKoToN
 * Version: 2.1.0
 * License: GPLv3
 * License URI: License URI: https://www.gnu.org/licenses/gpl-3.0-standalone.html
 * Text Domain: likoton-debug-logs
 * Requires PHP: 7.4
 * Requires at least: 6.0
 */

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

define( 'LIKOTON_DEBUG_LOGS_VERSION', '2.1.0' );
define( 'LIKOTON_DEBUG_LOGS_PLUGIN_DIR', plugin_dir_path( __FILE__ ) );
define( 'LIKOTON_DEBUG_LOGS_PLUGIN_URL', plugin_dir_url( __FILE__ ) );

require_once LIKOTON_DEBUG_LOGS_PLUGIN_DIR . 'includes/class-likoton-debug-logs-installer.php';
require_once LIKOTON_DEBUG_LOGS_PLUGIN_DIR . 'includes/class-likoton-debug-logs-logger.php';
require_once LIKOTON_DEBUG_LOGS_PLUGIN_DIR . 'includes/class-likoton-debug-logs-admin.php';
require_once LIKOTON_DEBUG_LOGS_PLUGIN_DIR . 'includes/class-likoton-debug-logs-assets.php';
require_once LIKOTON_DEBUG_LOGS_PLUGIN_DIR . 'includes/class-likoton-debug-logs-source-colors.php';

add_action( 'init', function () {
    // A language picked in the plugin settings overrides the WordPress locale for this plugin's texts only.
    // The catalogue is loaded directly: since WordPress 6.5 translations are looked up by the current
    // WordPress locale, so load_plugin_textdomain() with a different locale would never be used.
    $selected = \Likoton\DebugLogs\Likoton_Debug_Logs_Installer::get_selected_language();

    if ( '' !== $selected ) {
        require_once ABSPATH . WPINC . '/pomo/mo.php';

        $mo = new MO();

        if ( $mo->import_from_file( LIKOTON_DEBUG_LOGS_PLUGIN_DIR . 'languages/likoton-debug-logs-' . $selected . '.mo' ) ) {
            // phpcs:ignore WordPress.WP.GlobalVariablesOverride.Prohibited -- registering the plugin's own text domain.
            $GLOBALS['l10n']['likoton-debug-logs'] = $mo;
            return;
        }
    }

    // phpcs:ignore WordPress.WP.DeprecatedFunctions.load_plugin_textdomainFound, PluginCheck.CodeAnalysis.DiscouragedFunctions.load_plugin_textdomainFound
    load_plugin_textdomain( 'likoton-debug-logs', false, dirname( plugin_basename( __FILE__ ) ) . '/languages' );
} );

register_activation_hook( __FILE__, [ '\Likoton\DebugLogs\Likoton_Debug_Logs_Installer', 'activate' ] );
register_deactivation_hook( __FILE__, [ '\Likoton\DebugLogs\Likoton_Debug_Logs_Installer', 'deactivate' ] );
register_uninstall_hook( __FILE__, [ '\Likoton\DebugLogs\Likoton_Debug_Logs_Installer', 'uninstall' ] );

add_action( 'plugins_loaded', function () {
    \Likoton\DebugLogs\Likoton_Debug_Logs_Installer::maybe_upgrade();
    \Likoton\DebugLogs\Likoton_Debug_Logs_Logger::init();
    \Likoton\DebugLogs\Likoton_Debug_Logs_Admin::init();
    \Likoton\DebugLogs\Likoton_Debug_Logs_Assets::init();
    \Likoton\DebugLogs\Likoton_Debug_Logs_Source_Colors::init();
} );

add_filter( 'plugin_action_links_' . plugin_basename( __FILE__ ), function ( $links ) {
    $logs_url     = admin_url( 'admin.php?page=likoton-debug-logs-logs' );
    $settings_url = admin_url( 'admin.php?page=likoton-debug-logs-settings' );

    $links[] = '<a href="' . esc_url( $logs_url ) . '">' . esc_html__( 'Logs', 'likoton-debug-logs' ) . '</a>';
    $links[] = '<a href="' . esc_url( $settings_url ) . '">' . esc_html__( 'Settings', 'likoton-debug-logs' ) . '</a>';

    return $links;
} );
