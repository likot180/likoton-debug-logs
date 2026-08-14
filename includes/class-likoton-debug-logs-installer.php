<?php

namespace Likoton\DebugLogs;

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

class Likoton_Debug_Logs_Installer {

    const TABLE_NAME = 'likoton_debug_logs';

    /** Default colors for known sources */
    public static function get_default_source_colors() {
        return [
            'php'       => '#20c997',
            'wordpress' => '#0073aa',
            'wp'        => '#0073aa',
            'rest_api'  => '#6f42c1',
            'login'     => '#10b981',
        ];
    }

    public static function get_all_levels() {
        return [
            'debug', 'info', 'notice', 'warning', 'error',
            'critical', 'alert', 'emergency',
            'deprecated', 'user_deprecated', 'strict', 'parse',
            'core_error', 'core_warning', 'compile_error', 'compile_warning',
            'recoverable_error', 'user_error', 'user_warning', 'user_notice',
        ];
    }

    public static function activate() {
        global $wpdb;

        $table   = $wpdb->prefix . self::TABLE_NAME;
        $charset = $wpdb->get_charset_collate();

        $sql = "CREATE TABLE {$table} (
            id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
            level VARCHAR(50) NOT NULL,
            source VARCHAR(100) NOT NULL,
            message TEXT NOT NULL,
            context LONGTEXT NULL,
            ip VARCHAR(45) NULL,
            user_id BIGINT UNSIGNED NULL,
            created_at DATETIME NOT NULL,
            PRIMARY KEY (id),
            KEY level (level),
            KEY source (source),
            KEY created_at (created_at)
        ) {$charset};";

        require_once ABSPATH . 'wp-admin/includes/upgrade.php';
        dbDelta( $sql );

        if ( get_option( 'likoton_debug_logs_enabled_levels', null ) === null ) {
           update_option( 'likoton_debug_logs_enabled_levels', [
                'debug', 'info', 'notice', 'warning', 'error',
                'critical', 'alert', 'emergency',
                'deprecated', 'user_deprecated', 'strict', 'parse',
                'core_error', 'core_warning', 'compile_error', 'compile_warning',
                'recoverable_error', 'user_error', 'user_warning', 'user_notice',
            ] );
}
    }

    public static function deactivate() {
        wp_clear_scheduled_hook( 'likoton_debug_logs_cleanup_logs' );
    }

    public static function uninstall() {
        global $wpdb;
        $table = $wpdb->prefix . self::TABLE_NAME;
        // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, WordPress.DB.DirectDatabaseQuery.SchemaChange, WordPress.DB.PreparedSQL.InterpolatedNotPrepared
        $wpdb->query( "DROP TABLE IF EXISTS `{$table}`" );
    }

    public static function get_unique_sources() {
        global $wpdb;
        $table = $wpdb->prefix . self::TABLE_NAME;

        // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, WordPress.DB.PreparedSQL.InterpolatedNotPrepared
        return $wpdb->get_col( "SELECT DISTINCT source FROM `{$table}` ORDER BY source ASC" );
    }

    /**
     * Retrieve log entries from the database with optional filters.
     *
     * Security note: this method uses esc_sql() on all dynamically built SQL fragments
     * ($table from $wpdb->prefix + a hardcoded constant; $levels_in from a whitelist
     * validated against get_all_levels(); $where_extra contains only literal %s/%d
     * placeholders). PHPCS cannot statically verify esc_sql()-escaped variables and
     * flags them as unsafe — the disable block below suppresses those false positives.
     * User-supplied values (search, source) are always passed through $wpdb->prepare().
     */
    // phpcs:disable WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, WordPress.DB.PreparedSQL.NotPrepared, WordPress.DB.PreparedSQL.InterpolatedNotPrepared, WordPress.DB.PreparedSQLPlaceholders.ReplacementsWrongNumber, PluginCheck.Security.DirectDB.UnescapedDBParameter
    public static function get_logs( $args = [] ) {
        global $wpdb;

        $defaults = [
            'search'   => '',
            'level'    => '',
            'source'   => '',
            'page'     => 1,
            'per_page' => 50,
        ];
        $args = wp_parse_args( $args, $defaults );

        $search   = $args['search'];
        $level    = sanitize_key( $args['level'] );
        $source   = sanitize_key( $args['source'] );
        $page     = max( 1, (int) $args['page'] );
        $per_page = max( 1, (int) $args['per_page'] );
        $offset   = ( $page - 1 ) * $per_page;

        // Table name is built from $wpdb->prefix + a hardcoded constant — safe to use with esc_sql.
        $table = esc_sql( $wpdb->prefix . self::TABLE_NAME );

        $enabled_levels = get_option( 'likoton_debug_logs_enabled_levels', [] );
        $all_levels     = self::get_all_levels();

        // Determine which levels to show.
        if ( $level !== '' ) {
            // Specific level requested — validate against known levels, default to empty on unknown.
            $active_levels = in_array( $level, $all_levels, true ) ? [ $level ] : [ '__none__' ];
        } elseif ( empty( $enabled_levels ) ) {
            $active_levels = [ '__none__' ];
        } else {
            $active_levels = array_intersect( $enabled_levels, $all_levels );
        }

        // Sanitize each level slug — levels are known strings, esc_sql makes them DB-safe.
        $escaped_levels = array_map( function( $l ) {
            return "'" . esc_sql( $l ) . "'";
        }, $active_levels );
        $levels_in = implode( ', ', $escaped_levels );

        // Source and search filters.
        $where_extra = '';
        $params      = [];

        if ( $search !== '' ) {
            if ( ctype_digit( $search ) ) {
                $where_extra .= ' AND id = %d';
                $params[]     = (int) $search;
            } else {
                $where_extra .= ' AND message LIKE %s';
                $params[]     = '%' . $wpdb->esc_like( $search ) . '%';
            }
        }

        if ( $source !== '' ) {
            $where_extra .= ' AND source = %s';
            $params[]     = $source;
        }

        $params[] = $per_page;
        $params[] = $offset;

        // $table: esc_sql( $wpdb->prefix . CONSTANT ) — safe.
        // $levels_in: esc_sql() applied to each element individually — safe.
        // $where_extra: literal SQL with %s/%d placeholders only — safe.
        // No intermediate variable used — PHPCS tracks variable assignment as unsafe.
        return $wpdb->get_results( $wpdb->prepare( "SELECT * FROM `{$table}` WHERE level IN ( {$levels_in} ){$where_extra} ORDER BY id DESC LIMIT %d OFFSET %d", $params ) );
    }
    // phpcs:enable WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, WordPress.DB.PreparedSQL.NotPrepared, WordPress.DB.PreparedSQL.InterpolatedNotPrepared, WordPress.DB.PreparedSQLPlaceholders.ReplacementsWrongNumber, PluginCheck.Security.DirectDB.UnescapedDBParameter

}
