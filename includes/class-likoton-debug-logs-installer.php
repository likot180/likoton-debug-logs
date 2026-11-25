<?php

namespace Likoton\DebugLogs;

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

class Likoton_Debug_Logs_Installer {

    const TABLE_NAME         = 'likoton_debug_logs';
    const IGNORED_TABLE_NAME = 'likoton_debug_logs_ignored';

    /** Bump when the schema changes; maybe_upgrade() then re-runs dbDelta once. */
    const DB_VERSION = '2';

    /* Default colors for known sources */
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

        self::create_ignored_table();

        if ( get_option( 'likoton_debug_logs_enabled_levels', null ) === null ) {
           update_option( 'likoton_debug_logs_enabled_levels', [
                'debug', 'info', 'notice', 'warning', 'error',
                'critical', 'alert', 'emergency',
                'deprecated', 'user_deprecated', 'strict', 'parse',
                'core_error', 'core_warning', 'compile_error', 'compile_warning',
                'recoverable_error', 'user_error', 'user_warning', 'user_notice',
            ] );
}

        if ( get_option( 'likoton_debug_logs_dedup_interval', null ) === null ) {
            update_option( 'likoton_debug_logs_dedup_interval', 0 );
        }

        update_option( 'likoton_debug_logs_db_version', self::DB_VERSION );
    }

    /**
     * Records the user chose to ignore. A repeat of a matching log (same level, source and
     * message) is not stored in the logs table; its occurrence is counted here instead.
     */
    public static function create_ignored_table() {
        global $wpdb;

        $table   = $wpdb->prefix . self::IGNORED_TABLE_NAME;
        $charset = $wpdb->get_charset_collate();

        $sql = "CREATE TABLE {$table} (
            id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
            signature CHAR(32) NOT NULL,
            level VARCHAR(50) NOT NULL,
            source VARCHAR(100) NOT NULL,
            message TEXT NOT NULL,
            occurrences BIGINT UNSIGNED NOT NULL DEFAULT 0,
            created_at DATETIME NOT NULL,
            last_seen_at DATETIME NULL,
            PRIMARY KEY  (id),
            UNIQUE KEY signature (signature)
        ) {$charset};";

        require_once ABSPATH . 'wp-admin/includes/upgrade.php';
        dbDelta( $sql );
    }

    /** The plugin is updated in place (no activation), so new tables are created here. */
    public static function maybe_upgrade() {
        if ( get_option( 'likoton_debug_logs_db_version' ) === self::DB_VERSION ) {
            return;
        }

        self::create_ignored_table();
        update_option( 'likoton_debug_logs_db_version', self::DB_VERSION );
    }

    /** Identity of a log for the ignore list: level + source + message, as stored. */
    public static function get_signature( $level, $source, $message ) {
        return md5( $level . "\0" . $source . "\0" . $message );
    }

    public static function deactivate() {
        wp_clear_scheduled_hook( 'likoton_debug_logs_cleanup_logs' );
    }

    public static function uninstall() {
        global $wpdb;

        foreach ( [ self::TABLE_NAME, self::IGNORED_TABLE_NAME ] as $name ) {
            $table = esc_sql( $wpdb->prefix . $name );
            // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, WordPress.DB.DirectDatabaseQuery.SchemaChange, WordPress.DB.PreparedSQL.InterpolatedNotPrepared, PluginCheck.Security.DirectDB.UnescapedDBParameter
            $wpdb->query( "DROP TABLE IF EXISTS `{$table}`" );
        }
    }

    /** Columns the ignored list can be sorted by. */
    public static function get_ignored_sortable() {
        return [ 'level', 'source', 'message', 'occurrences', 'last_seen_at', 'created_at' ];
    }

    /**
     * Ignored records, optionally filtered (search in message/source, exact level, exact source)
     * and sorted by a whitelisted column. Defaults to newest added first.
     */
    public static function get_ignored( $args = [] ) {
        global $wpdb;

        $args  = wp_parse_args( $args, [
            'search'  => '',
            'level'   => '',
            'source'  => '',
            'orderby' => 'created_at',
            'order'   => 'DESC',
        ] );
        $table = esc_sql( $wpdb->prefix . self::IGNORED_TABLE_NAME );

        $where  = [];
        $params = [];

        if ( '' !== $args['search'] ) {
            $like     = '%' . $wpdb->esc_like( $args['search'] ) . '%';
            $where[]  = '( message LIKE %s OR source LIKE %s OR level LIKE %s )';
            $params[] = $like;
            $params[] = $like;
            $params[] = $like;
        }

        if ( '' !== $args['level'] ) {
            $where[]  = 'level = %s';
            $params[] = $args['level'];
        }

        if ( '' !== $args['source'] ) {
            $where[]  = 'source = %s';
            $params[] = $args['source'];
        }

        $orderby = in_array( $args['orderby'], self::get_ignored_sortable(), true ) ? esc_sql( $args['orderby'] ) : 'created_at';
        $order   = 'ASC' === strtoupper( $args['order'] ) ? 'ASC' : 'DESC';

        $sql = "SELECT * FROM `{$table}`";

        if ( $where ) {
            $sql .= ' WHERE ' . implode( ' AND ', $where );
        }

        // $orderby/$order come from whitelists above; id keeps the order stable between equal values.
        $sql .= " ORDER BY {$orderby} {$order}, id {$order}";

        if ( $params ) {
            $sql = $wpdb->prepare( $sql, $params ); // phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared, PluginCheck.Security.DirectDB.UnescapedDBParameter
        }

        // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, WordPress.DB.PreparedSQL.NotPrepared, PluginCheck.Security.DirectDB.UnescapedDBParameter
        return $wpdb->get_results( $sql );
    }

    /** Distinct levels and sources present on the ignore list (for the filter dropdowns). */
    public static function get_ignored_filter_values( $column ) {
        global $wpdb;

        if ( ! in_array( $column, [ 'level', 'source' ], true ) ) {
            return [];
        }

        $table  = esc_sql( $wpdb->prefix . self::IGNORED_TABLE_NAME );
        $column = esc_sql( $column );

        // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, WordPress.DB.PreparedSQL.InterpolatedNotPrepared, PluginCheck.Security.DirectDB.UnescapedDBParameter
        return $wpdb->get_col( "SELECT DISTINCT {$column} FROM `{$table}` ORDER BY {$column} ASC" );
    }

    /** Interface languages shipped with the plugin (locale => native name); only those with a compiled .mo. */
    public static function get_available_languages() {
        $names = [
            'en_US' => 'English (US)',
            'pl_PL' => 'Polski',
            'de_DE' => 'Deutsch',
            'fr_FR' => 'Français',
            'es_ES' => 'Español',
            'cs_CZ' => 'Čeština',
            'sk_SK' => 'Slovenčina',
            'pt_PT' => 'Português',
        ];

        foreach ( array_keys( $names ) as $locale ) {
            if ( ! file_exists( LIKOTON_DEBUG_LOGS_PLUGIN_DIR . 'languages/likoton-debug-logs-' . $locale . '.mo' ) ) {
                unset( $names[ $locale ] );
            }
        }

        return $names;
    }

    /** The language chosen in the settings, or '' for "System default" (follow the WordPress locale). */
    public static function get_selected_language() {
        $language = (string) get_option( 'likoton_debug_logs_language', '' );

        return isset( self::get_available_languages()[ $language ] ) ? $language : '';
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
            'search'    => '',
            'level'     => '',
            'source'    => '',
            'page'      => 1,
            'per_page'  => 50,
            'date_from' => '',
            'date_to'   => '',
            'since'     => '',
            'orderby'   => 'created_at',
            'order'     => 'DESC',
        ];
        $args = wp_parse_args( $args, $defaults );

        $search    = $args['search'];
        $level     = sanitize_key( $args['level'] );
        $source    = sanitize_key( $args['source'] );
        $page      = max( 1, (int) $args['page'] );
        $per_page  = max( 1, (int) $args['per_page'] );
        $offset    = ( $page - 1 ) * $per_page;
        $date_from = sanitize_text_field( $args['date_from'] );
        $date_to   = sanitize_text_field( $args['date_to'] );
        $since     = sanitize_text_field( $args['since'] );

        // Only known columns may reach the ORDER BY clause.
        $allowed_orderby = [ 'id', 'level', 'source', 'message', 'created_at' ];
        $orderby         = in_array( $args['orderby'], $allowed_orderby, true ) ? $args['orderby'] : 'created_at';
        $order           = strtoupper( $args['order'] ) === 'ASC' ? 'ASC' : 'DESC';

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

        $valid_date = '/^\d{4}-\d{2}-\d{2}$/';

        if ( $date_from !== '' && $date_to !== '' && preg_match( $valid_date, $date_from ) && preg_match( $valid_date, $date_to ) ) {
            if ( $date_from > $date_to ) {
                [ $date_from, $date_to ] = [ $date_to, $date_from ];
            }

            $site_tz = wp_timezone();
            $utc_tz  = new \DateTimeZone( 'UTC' );

            $range_start = \DateTime::createFromFormat( 'Y-m-d H:i:s', $date_from . ' 00:00:00', $site_tz );
            $range_end   = \DateTime::createFromFormat( 'Y-m-d H:i:s', $date_to . ' 23:59:59', $site_tz );

            if ( $range_start && $range_end ) {
                $range_start->setTimezone( $utc_tz );
                $range_end->setTimezone( $utc_tz );

                $where_extra .= ' AND created_at BETWEEN %s AND %s';
                $params[]     = $range_start->format( 'Y-m-d H:i:s' );
                $params[]     = $range_end->format( 'Y-m-d H:i:s' );
            }
        }

        if ( $since !== '' && preg_match( '/^\d{4}-\d{2}-\d{2} \d{2}:\d{2}:\d{2}$/', $since ) ) {
            $where_extra .= ' AND created_at >= %s';
            $params[]     = $since;
        }

        $params[] = $per_page;
        $params[] = $offset;

        // $orderby is whitelisted above; add id as a stable tiebreaker for non-unique columns.
        $order_sql = "{$orderby} {$order}";
        if ( 'id' !== $orderby ) {
            $order_sql .= ", id {$order}";
        }

        // $table: esc_sql( $wpdb->prefix . CONSTANT ) — safe.
        // $levels_in: esc_sql() applied to each element individually — safe.
        // $where_extra: literal SQL with %s/%d placeholders only — safe.
        // $order_sql: built only from the whitelisted $orderby/$order above — safe.
        // No intermediate variable used — PHPCS tracks variable assignment as unsafe.
        return $wpdb->get_results( $wpdb->prepare( "SELECT * FROM `{$table}` WHERE level IN ( {$levels_in} ){$where_extra} ORDER BY {$order_sql} LIMIT %d OFFSET %d", $params ) );
    }
    // phpcs:enable WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, WordPress.DB.PreparedSQL.NotPrepared, WordPress.DB.PreparedSQL.InterpolatedNotPrepared, WordPress.DB.PreparedSQLPlaceholders.ReplacementsWrongNumber, PluginCheck.Security.DirectDB.UnescapedDBParameter

}
