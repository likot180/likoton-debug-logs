<?php
namespace Likoton\DebugLogs;

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

/**
 * Admin interface for LiKoToN Debug Logs
 */
class LDL_Admin {

    public static function init() {
        add_action( 'admin_menu', [ __CLASS__, 'menu' ] );
		add_action( 'admin_post_ldl_delete_logs', [ __CLASS__, 'handle_delete_logs' ] );
		add_action( 'admin_post_ldl_export_logs', [ __CLASS__, 'handle_export_logs' ] );
		add_action( 'wp_ajax_ldl_save_settings', [ __CLASS__, 'ajax_save_settings' ] );
		add_filter( 'cron_schedules', [ __CLASS__, 'add_schedules' ] );
		
		if ( ! wp_next_scheduled( 'ldl_cleanup_logs' ) ) {
			wp_schedule_event( time(), 'daily', 'ldl_cleanup_logs' );
		}

		add_action( 'ldl_cleanup_logs', [ __CLASS__, 'cleanup_logs' ] );
    }
	
	/**
     * Schedules 
     */
    public static function add_schedules( $schedules ) {
		$schedules['every_30_minutes'] = [
			'interval' => 30 * 60,
			'display'  => __( 'Every 30 minutes', 'likoton-debug-logs' ),
		];

		$schedules['every_hour'] = [
			'interval' => 60 * 60,
			'display'  => __( 'Every hour', 'likoton-debug-logs' ),
		];

		return $schedules;
	}

	/**
     * Auto-save
     */
	public static function ajax_save_settings() {
		check_ajax_referer( 'ldl_save_settings', 'ldl_nonce' );

		$dark_mode  = isset( $_POST['ldl_dark_mode'] ) ? 1 : 0;
		$capability = isset( $_POST['ldl_capability'] )
			? sanitize_text_field( wp_unslash( $_POST['ldl_capability'] ) )
			: 'manage_options';

		$retention = isset( $_POST['ldl_log_retention'] )
			? sanitize_text_field( wp_unslash( $_POST['ldl_log_retention'] ) )
			: '1m';

		update_option( 'ldl_dark_mode', $dark_mode );
		update_option( 'ldl_capability', $capability );
		update_option( 'ldl_log_retention', $retention );

		wp_clear_scheduled_hook( 'ldl_cleanup_logs' );

		switch ( $retention ) {
			case '30m':
				wp_schedule_event( time() + 30 * 60, 'every_30_minutes', 'ldl_cleanup_logs' );
				break;

			case '1h':
				wp_schedule_event( time() + 60 * 60, 'every_hour', 'ldl_cleanup_logs' );
				break;

			default:
				wp_schedule_event( time() + DAY_IN_SECONDS, 'daily', 'ldl_cleanup_logs' );
		}

		wp_send_json_success();
		wp_die();
	}

	/**
     * Automatic log cleanup
     */

	public static function cleanup_logs() {
		$retention = get_option( 'ldl_log_retention', '1m' );

		switch ( $retention ) {
			case '30m': $interval = '30 MINUTE'; break;
			case '1h':  $interval = '1 HOUR'; break;
			case '12h': $interval = '12 HOUR'; break;
			case '1d':  $interval = '1 DAY'; break;
			case '1w':  $interval = '7 DAY'; break;
			case '2w':  $interval = '14 DAY'; break;
			case '1m':  $interval = '30 DAY'; break;
			default:    $interval = '30 DAY';
		}

		global $wpdb;
		$table = $wpdb->prefix . LDL_Installer::TABLE_NAME;

		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.PreparedSQL.InterpolatedNotPrepared
		$wpdb->query(
			"DELETE FROM $table WHERE created_at < (NOW() - INTERVAL $interval)"
		);
		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.PreparedSQL.InterpolatedNotPrepared
		$wpdb->query( "OPTIMIZE TABLE $table" );
	}

    /**
     * Register admin menu
     */
    public static function menu() {
        $capability = get_option( 'ldl_capability', 'manage_options' );

        // Main menu
        add_menu_page(
            __( 'Logs', 'likoton-debug-logs' ),
            'LiKoToN Debug Logs',
            $capability,
            'ldl-logs',
            [ __CLASS__, 'render_logs_page' ],
            'dashicons-list-view',
            80
        );

        // Submenu: Logs
        add_submenu_page(
            'ldl-logs',
            __( 'Logs', 'likoton-debug-logs' ),
            __( 'Logs', 'likoton-debug-logs' ),
            $capability,
            'ldl-logs',
            [ __CLASS__, 'render_logs_page' ]
        );

        // Submenu: Settings
        add_submenu_page(
            'ldl-logs',
            __( 'Settings', 'likoton-debug-logs' ),
            __( 'Settings', 'likoton-debug-logs' ),
            $capability,
            'ldl-settings',
            [ __CLASS__, 'render_settings_page' ]
        );
		
		// Hidden submenu: Donation
		add_submenu_page(
			null,
			__( 'Donation', 'likoton-debug-logs' ),
			__( 'Donation', 'likoton-debug-logs' ),
			$capability,
			'ldl-donation',
			[ __CLASS__, 'render_donation_page' ]
		);
    }
	
	/**
     * Header
     */
	public static function render_header() {
		?>
			<h1>LiKoToN Debug Logs</h1>
		<?php
	}

    /**
     * Render Logs page
     */
	public static function render_logs_page() {
		?>
		<div class="wrap">
			<?php
			self::render_header();

			if ( isset( $_GET['deleted'] ) ) : ?>
				<div class="notice notice-success is-dismissible">
					<p><?php esc_html_e( 'All logs deleted.', 'likoton-debug-logs' ); ?></p>
				</div>
			<?php endif; ?>

			<?php self::render_tabs( 'logs' ); ?>
			<?php self::render_logs_content(); ?>
		</div>
		<?php
	}
	
	/**
     * Render Settings page
     */
	public static function render_settings_page() {

		if ( isset( $_POST['ldl_settings_action'] ) && check_admin_referer( 'ldl_save_settings' ) ) {

			$dark_mode  = isset( $_POST['ldl_dark_mode'] ) ? 1 : 0;
			$capability = isset( $_POST['ldl_capability'] )
				? sanitize_text_field( wp_unslash( $_POST['ldl_capability'] ) )
				: 'manage_options';

			$cron_disabled = defined('DISABLE_WP_CRON') && DISABLE_WP_CRON;
			$retention = isset( $_POST['ldl_log_retention'] )
				? sanitize_text_field( wp_unslash( $_POST['ldl_log_retention'] ) )
				: '1m';

			update_option( 'ldl_dark_mode', $dark_mode );
			update_option( 'ldl_capability', $capability );
			update_option( 'ldl_log_retention', $retention );
		}

		$dark_mode  = (bool) get_option( 'ldl_dark_mode', 0 );
		$capability = get_option( 'ldl_capability', 'manage_options' );
		$retention  = get_option( 'ldl_log_retention', '1m' );
		
	?>
	
		<div id="ldl-toast" class="ldl-toast" style="display:none;">
			✔ <?php esc_html_e( 'Saved', 'likoton-debug-logs' ); ?>
		</div>
	
        <div class="wrap">
            <?php
			self::render_header();
			self::render_tabs( 'settings' ); ?>

            <form method="post" id="ldl-settings-form">
                <?php wp_nonce_field( 'ldl_save_settings', 'ldl_nonce' ); ?>
                <input type="hidden" name="ldl_settings_action" value="1" />

                <table class="form-table">

                    <!-- Dark mode toggle (WordPress 7 style) -->
                    <tr>
                        <th scope="row"><?php esc_html_e( 'Dark mode', 'likoton-debug-logs' ); ?></th>
                        <td>
                            <label class="components-form-toggle">
                                <input type="checkbox"
                                       class="components-form-toggle__input"
                                       name="ldl_dark_mode"
                                       id="ldl_dark_mode"
                                       value="1"
                                       <?php checked( $dark_mode ); ?> />
                                <span class="components-form-toggle__track"></span>
                                <span class="components-form-toggle__thumb"></span>
                            </label>
                        </td>
                    </tr>

                    <!-- Log cleanup -->
					<tr>
						<th scope="row"><?php esc_html_e( 'Log retention', 'likoton-debug-logs' ); ?></th>
						<td>
							<select name="ldl_log_retention" <?php disabled( $cron_disabled ); ?>>
								<option value="30m" <?php selected( $retention, '30m' ); ?>><?php esc_html_e( '30 minutes', 'likoton-debug-logs' ); ?></option>
								<option value="1h" <?php selected( $retention, '1h' ); ?>><?php esc_html_e( '1 hour', 'likoton-debug-logs' ); ?></option>
								<option value="12h" <?php selected( $retention, '12h' ); ?>><?php esc_html_e( '12 hours', 'likoton-debug-logs' ); ?></option>
								<option value="1d"  <?php selected( $retention, '1d' ); ?>><?php esc_html_e( '1 day', 'likoton-debug-logs' ); ?></option>
								<option value="1w"  <?php selected( $retention, '1w' ); ?>><?php esc_html_e( '1 week', 'likoton-debug-logs' ); ?></option>
								<option value="2w"  <?php selected( $retention, '2w' ); ?>><?php esc_html_e( '2 weeks', 'likoton-debug-logs' ); ?></option>
								<option value="1m"  <?php selected( $retention, '1m' ); ?>><?php esc_html_e( '1 month', 'likoton-debug-logs' ); ?></option>
							</select>
							<?php if ( $cron_disabled ) : ?>
								<p class="description" style="color:#cc0000;">
									<?php esc_html_e( 'WP-Cron is disabled. Log cleanup will not run automatically.', 'likoton-debug-logs' ); ?>
								</p>
							<?php endif; ?>
						</td>
					</tr>

                    <!-- Capability -->
                    <tr>
                        <th scope="row"><?php esc_html_e( 'Capability', 'likoton-debug-logs' ); ?></th>
                        <td>
							<select name="ldl_capability" id="ldl_capability">
								<option value="manage_options" <?php selected( $capability, 'manage_options' ); ?>>
									<?php esc_html_e( 'Administrator (full access)', 'likoton-debug-logs' ); ?>
								</option>
								<option value="edit_posts" <?php selected( $capability, 'edit_posts' ); ?>>
									<?php esc_html_e( 'Editor (can edit posts)', 'likoton-debug-logs' ); ?>
								</option>
								<option value="list_users" <?php selected( $capability, 'list_users' ); ?>>
									<?php esc_html_e( 'Moderator (can list users)', 'likoton-debug-logs' ); ?>
								</option>
							</select>
                            <p class="description"><?php esc_html_e( 'Minimum capability required to view logs.', 'likoton-debug-logs' ); ?></p>
                        </td>
                    </tr>

                </table>

            </form>
        </div>
        <?php
    }
	
	public static function render_donation_page() {
    ?>
    <div class="wrap">
        <?php
		self::render_header();
		self::render_tabs( 'donation' ); ?>

        <p class="ldl-donation"><?php esc_html_e( 'If you like what I do, please consider a small donation to support further development.', 'likoton-debug-logs' ); ?></p>

        <div class="ldl-donation-box">
            <a href="https://buycoffee.to/likoton" target="_blank">
                <img src="<?php echo esc_url( LDL_PLUGIN_URL . 'images/buycoffee.png' ); ?>" alt="Buy me a coffee" />
            </a>
			<a href="https://revolut.me/piotr7c5k" target="_blank">
                <img src="<?php echo esc_url( LDL_PLUGIN_URL . 'images/revolut.png' ); ?>" alt="Buy me a coffee" />
            </a>
		</div>
		<p class="ldl-donation"><?php esc_html_e( 'Thank you!', 'likoton-debug-logs' ); ?></p>
    </div>
    <?php
}

    /**
     * Render tabs navigation
     */
	private static function render_tabs( $active = 'logs' ) {
		?>
		<nav class="health-check-tabs-wrapper hide-if-no-js tab-count-3"
			 aria-label="<?php esc_attr_e( 'Bookmark menu', 'likoton-debug-logs' ); ?>">

			<a href="<?php echo esc_url( admin_url( 'admin.php?page=ldl-logs' ) ); ?>"
			   class="health-check-tab <?php echo $active === 'logs' ? 'active' : ''; ?>"
			   <?php echo $active === 'logs' ? 'aria-current="page"' : ''; ?>>
				<?php esc_html_e( 'Logs', 'likoton-debug-logs' ); ?>
			</a>

			<a href="<?php echo esc_url( admin_url( 'admin.php?page=ldl-settings' ) ); ?>"
			   class="health-check-tab <?php echo $active === 'settings' ? 'active' : ''; ?>"
			   <?php echo $active === 'settings' ? 'aria-current="page"' : ''; ?>>
				<?php esc_html_e( 'Settings', 'likoton-debug-logs' ); ?>
			</a>
			
			<a href="<?php echo esc_url( admin_url( 'admin.php?page=ldl-donation' ) ); ?>"
			   class="health-check-tab <?php echo $active === 'donation' ? 'active' : ''; ?>"
			   <?php echo $active === 'donation' ? 'aria-current="page"' : ''; ?>>
				<?php esc_html_e( 'Donation', 'likoton-debug-logs' ); ?>
			</a>

		</nav>
		<?php
	}

    /**
     * Format log level
     */
    private static function format_level( $level ) {
        return ucwords( str_replace( '_', ' ', $level ) );
    }
	
	/**
	 * Format UTC date to local WordPress time
	 */
	public static function format_local_date( $utc_string ) {

		if ( empty( $utc_string ) ) {
			return '';
		}

		$timestamp = strtotime( $utc_string . ' UTC' );

		$local = wp_date(
			get_option( 'date_format' ) . ' ' . get_option( 'time_format' ),
			$timestamp
		);

		return sprintf(
			'<span title="%s UTC">%s</span>',
			esc_attr( $utc_string ),
			esc_html( $local )
		);
	}
	
	
    /**
     * Delete logs
     */
	public static function handle_delete_logs() {

		if ( ! current_user_can( get_option( 'ldl_capability', 'manage_options' ) ) ) {
			wp_die( 'No permission' );
		}

		check_admin_referer( 'ldl_delete_logs', 'ldl_delete_logs_nonce' );

		global $wpdb;
		$table = $wpdb->prefix . LDL_Installer::TABLE_NAME;

		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.PreparedSQL.InterpolatedNotPrepared
		$wpdb->query( "TRUNCATE TABLE $table" );

		wp_safe_redirect( admin_url( 'admin.php?page=ldl-logs&deleted=1' ) );
		exit;
	}

    /**
     * Export logs
     */
	public static function handle_export_logs() {

		if ( ! current_user_can( get_option( 'ldl_capability', 'manage_options' ) ) ) {
			wp_die( 'No permission' );
		}

		check_admin_referer( 'ldl_export_logs', 'ldl_export_logs_nonce' );

		global $wpdb;
		$table = $wpdb->prefix . LDL_Installer::TABLE_NAME;

		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.PreparedSQL.InterpolatedNotPrepared
		$rows = $wpdb->get_results( "SELECT * FROM $table ORDER BY id ASC", ARRAY_A );

		nocache_headers();
		header( 'Content-Type: text/csv; charset=utf-8' );
		header( 'Content-Disposition: attachment; filename=ldl-logs.csv' );

		$output = fopen( 'php://output', 'w' );

		if ( ! empty( $rows ) ) {
			fputcsv( $output, array_keys( $rows[0] ) );
			foreach ( $rows as $row ) {
				fputcsv( $output, $row );
			}
		}

		// phpcs:ignore WordPress.WP.AlternativeFunctions.file_system_operations_fclose
		fclose( $output );
		exit;
	}

    /**
     * Render logs table and filters
     */
    public static function render_logs_content() {

		// phpcs:ignore WordPress.Security.NonceVerification.Recommended
        $search = isset( $_GET['s'] ) ? sanitize_text_field( wp_unslash( $_GET['s'] ) ) : '';
        $last   = isset( $_GET['last'] ) ? (int) $_GET['last'] : 50;
        $level  = isset( $_GET['level'] ) ? sanitize_text_field( wp_unslash( $_GET['level'] ) ) : '';
		$source = isset( $_GET['source'] ) ? sanitize_text_field( wp_unslash( $_GET['source'] ) ) : '';

        $logs = LDL_Installer::get_logs( [
            'search' => $search,
            'last'   => $last,
            'level'  => $level,
			'source' => $source,
        ] );
        ?>
        <form id="ldl-filters" method="get" action="<?php echo esc_url( admin_url( 'admin.php' ) ); ?>">
            <input type="hidden" name="page" value="ldl-logs" />

            <div class="ldl-filters">

                <div class="ldl-search-wrapper">
					<input type="search" name="s" value="<?php echo esc_attr( $search ); ?>"
						   placeholder="<?php esc_attr_e( 'Search logs…', 'likoton-debug-logs' ); ?>" />
					<span class="ldl-clear-search">×</span>
				</div>


                <select name="level">
                    <option value=""><?php esc_html_e( 'All levels', 'likoton-debug-logs' ); ?></option>
                    <?php
                    $levels = [
                        'debug', 'info', 'notice', 'warning', 'error',
                        'critical', 'alert', 'emergency',
                        'deprecated', 'user_deprecated', 'strict', 'parse',
                        'core_error', 'core_warning', 'compile_error', 'compile_warning',
                        'recoverable_error', 'user_error', 'user_warning', 'user_notice',
                    ];
                    foreach ( $levels as $lvl ) :
                        ?>
                        <option value="<?php echo esc_attr( $lvl ); ?>" <?php selected( $level, $lvl ); ?>>
                            <?php echo esc_html( self::format_level( $lvl ) ); ?>
                        </option>
                    <?php endforeach; ?>
                </select>
				
				<select name="source">
					<option value=""><?php esc_html_e( 'All sources', 'likoton-debug-logs' ); ?></option>
					<?php
					$sources = LDL_Installer::get_unique_sources();
					foreach ( $sources as $src ) :
					?>
						<option value="<?php echo esc_attr( $src ); ?>" <?php selected( $source, $src ); ?>>
							<?php echo esc_html( $src ); ?>
						</option>
					<?php endforeach; ?>
				</select>


                <select name="last">
                    <option value="20" <?php selected( $last, 20 ); ?>><?php esc_html_e( 'Last 20', 'likoton-debug-logs' ); ?></option>
                    <option value="50" <?php selected( $last, 50 ); ?>><?php esc_html_e( 'Last 50', 'likoton-debug-logs' ); ?></option>
                    <option value="100" <?php selected( $last, 100 ); ?>><?php esc_html_e( 'Last 100', 'likoton-debug-logs' ); ?></option>
                    <option value="200" <?php selected( $last, 200 ); ?>><?php esc_html_e( 'Last 200', 'likoton-debug-logs' ); ?></option>
					<option value="500" <?php selected( $last, 500 ); ?>><?php esc_html_e( 'Last 500', 'likoton-debug-logs' ); ?></option>
					<option value="1000" <?php selected( $last, 1000 ); ?>><?php esc_html_e( 'Last 1000', 'likoton-debug-logs' ); ?></option>
					<option value="all" <?php selected( $last, 'all' ); ?>><?php esc_html_e( 'All logs', 'likoton-debug-logs' ); ?></option>
                </select>

            </div>

			<div class="ldl-logs-wrapper">
				<table id="ldl-logs-table" class="wp-list-table widefat fixed striped ldl-logs">
					<thead>
					<tr>
						<th class="column-id">ID</th>
						<th class="column-level"><?php esc_html_e( 'Level', 'likoton-debug-logs' ); ?></th>
						<th class="column-source"><?php esc_html_e( 'Source', 'likoton-debug-logs' ); ?></th>
						<th class="column-message"><?php esc_html_e( 'Message', 'likoton-debug-logs' ); ?></th>
						<th class="column-date"><?php esc_html_e( 'Date', 'likoton-debug-logs' ); ?></th>
					</tr>
					</thead>
					<tbody>
					<?php if ( $logs ) : ?>
						<?php foreach ( $logs as $row ) : ?>
							<tr>
								<td><?php echo (int) $row->id; ?></td>
								<td>
									<span class="ldl-badge ldl-level-<?php echo esc_attr( $row->level ); ?>">
										<?php echo esc_html( self::format_level( $row->level ) ); ?>
									</span>
								</td>
								<td>
									<span class="ldl-badge ldl-source-<?php echo esc_attr( $row->source ); ?>">
										<?php echo esc_html( $row->source ); ?>
									</span>
								</td>
								<td><?php echo esc_html( $row->message ); ?></td>
								<td><?php echo
								// phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
								self::format_local_date( $row->created_at ); ?></td>
							</tr>
						<?php endforeach; ?>
					<?php else : ?>
						<tr>
							<td colspan="5"><?php esc_html_e( 'No logs found.', 'likoton-debug-logs' ); ?></td>
						</tr>
					<?php endif; ?>
					</tbody>
				</table>
			</div>
        </form>
		
		<div class="ldl-actions">
			<form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>" class="ldl-delete-form">
				<?php wp_nonce_field( 'ldl_delete_logs', 'ldl_delete_logs_nonce' ); ?>
				<input type="hidden" name="action" value="ldl_delete_logs" />
				<button class="button button-secondary">
					<?php esc_html_e( 'Delete all logs', 'likoton-debug-logs' ); ?>
				</button>
			</form>
				
			<form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>">
				<?php wp_nonce_field( 'ldl_export_logs', 'ldl_export_logs_nonce' ); ?>
				<input type="hidden" name="action" value="ldl_export_logs" />
				<button class="button button-primary">
					<?php esc_html_e( 'Export logs (CSV)', 'likoton-debug-logs' ); ?>
				</button>
			</form>
        </div>

        <?php
    }
}
