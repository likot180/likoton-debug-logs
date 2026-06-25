<?php

namespace Likoton\DebugLogs;

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

class LDL_Installer {

    const TABLE_NAME = 'ldl_logs';

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

        if ( get_option( 'ldl_enabled_levels', null ) === null ) {
           update_option( 'ldl_enabled_levels', [
                'debug', 'info', 'notice', 'warning', 'error',
                'critical', 'alert', 'emergency',
                'deprecated', 'user_deprecated', 'strict', 'parse',
                'core_error', 'core_warning', 'compile_error', 'compile_warning',
                'recoverable_error', 'user_error', 'user_warning', 'user_notice',
            ] );
        }
    }

    public static function deactivate() {
        wp_clear_scheduled_hook( 'ldl_cleanup_logs' );
    }

    public static function uninstall() {
        global $wpdb;
        $table = $wpdb->prefix . self::TABLE_NAME;
        // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.PreparedSQL.InterpolatedNotPrepared, WordPress.DB.DirectDatabaseQuery.SchemaChangeInterpolatedNotPrepared
        $wpdb->query( "DROP TABLE IF EXISTS {$table}" );
    }

    public static function get_unique_sources() {
        global $wpdb;
        $table = $wpdb->prefix . self::TABLE_NAME;

        // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.PreparedSQL.InterpolatedNotPrepared
        return $wpdb->get_col( "SELECT DISTINCT source FROM $table ORDER BY source ASC" );
    }

    public static function get_logs( $args = [] ) {
        global $wpdb;

        $table = $wpdb->prefix . self::TABLE_NAME;

        $defaults = [
            'search'   => '',
            'level'    => '',
            'source'   => '',
            'page'     => 1,
            'per_page' => 50,
        ];
        $args = wp_parse_args( $args, $defaults );

        $where   = '1=1';
        $params  = [];

        if ( $args['search'] !== '' ) {
            if ( ctype_digit( $args['search'] ) ) {
                $where   .= ' AND id = %d';
                $params[] = (int) $args['search'];
            } else {
                $where   .= ' AND message LIKE %s';
                $params[] = '%' . $wpdb->esc_like( $args['search'] ) . '%';
            }
        }

        $enabled_levels = get_option( 'ldl_enabled_levels', [] );

        if ( $args['level'] !== '' ) {
            $where .= ' AND level = %s';
            $params[] = $args['level'];
        } elseif ( ! empty( $enabled_levels ) ) {
            $placeholders = implode( ',', array_fill( 0, count( $enabled_levels ), '%s' ) );
            $where .= " AND level IN ($placeholders)";
            $params = array_merge( $params, $enabled_levels );
        }

        if ( $args['source'] !== '' ) {
            $where   .= ' AND source = %s';
            $params[] = $args['source'];
        }

        $page     = max( 1, (int) $args['page'] );
        $per_page = max( 1, (int) $args['per_page'] );
        $offset   = ( $page - 1 ) * $per_page;

        $sql = "SELECT * FROM {$table} WHERE {$where} ORDER BY id DESC LIMIT %d OFFSET %d";
        $params[] = $per_page;
        $params[] = $offset;

        return $wpdb->get_results( $wpdb->prepare( $sql, $params ) );
    }

}
