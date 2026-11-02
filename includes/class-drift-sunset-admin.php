<?php
/**
 * Tools → Drift: Sunset: an overview of upcoming and recent expiries, and a
 * Settings tab for admins (Drift_Sunset_Settings).
 *
 * Expiry dates themselves are set per post from the edit screen.
 *
 * @package Drift_Sunset
 */

defined( 'ABSPATH' ) || exit;

/**
 * Admin screen.
 */
final class Drift_Sunset_Admin {

	const SLUG = 'drift-sunset';

	/**
	 * Editors as well as admins schedule content, so they can see the list.
	 * The Tools menu is visible to editors, which is why the screen lives there.
	 */
	const CAPABILITY = 'edit_others_posts';

	/**
	 * Rows per table.
	 */
	const UPCOMING_LIMIT = 50;
	const EXPIRED_LIMIT  = 20;

	/**
	 * Seconds the hourly sweep can be overdue before we say cron isn't
	 * running. An hour of slack covers quiet sites, where WP-Cron waits for
	 * the next visit, and server crons that only run every 15 to 30 minutes.
	 */
	const CRON_STALL_AFTER = 3600;

	/**
	 * Hook suffix of the Tools page, set when the menu is added.
	 *
	 * @var string
	 */
	private static $hook_suffix = '';

	/**
	 * Hook in.
	 *
	 * @return void
	 */
	public static function init() {
		add_action( 'admin_menu', array( __CLASS__, 'add_menu' ) );
		add_action( 'admin_enqueue_scripts', array( __CLASS__, 'assets' ) );
		add_filter( 'admin_footer_text', array( __CLASS__, 'footer_text' ) );
		add_filter( 'plugin_action_links_' . plugin_basename( DRIFT_SUNSET_FILE ), array( __CLASS__, 'action_links' ) );
	}

	/**
	 * Add Tools → Drift: Sunset.
	 *
	 * @return void
	 */
	public static function add_menu() {
		self::$hook_suffix = (string) add_management_page(
			__( 'Drift: Sunset', 'drift-sunset' ),
			__( 'Drift: Sunset', 'drift-sunset' ),
			self::CAPABILITY,
			self::SLUG,
			array( __CLASS__, 'render' )
		);
	}

	/**
	 * URL of the screen, optionally on a tab.
	 *
	 * @param string $tab Tab key, or '' for the default.
	 * @return string
	 */
	public static function url( $tab = '' ) {
		$url = admin_url( 'tools.php?page=' . self::SLUG );

		return $tab ? add_query_arg( 'tab', $tab, $url ) : $url;
	}

	/**
	 * Tabs the current user can see.
	 *
	 * @return array<string, array{label: string, render: callable}>
	 */
	private static function tabs() {
		$tabs = array(
			'overview' => array(
				'label'  => __( 'Overview', 'drift-sunset' ),
				'render' => array( __CLASS__, 'render_overview' ),
			),
		);

		if ( current_user_can( Drift_Sunset_Settings::CAPABILITY ) ) {
			$tabs['settings'] = array(
				'label'  => __( 'Settings', 'drift-sunset' ),
				'render' => array( 'Drift_Sunset_Settings', 'render' ),
			);
		}

		return $tabs;
	}

	/**
	 * Whether the current admin screen is this one.
	 *
	 * @return bool
	 */
	private static function is_screen() {
		$screen = function_exists( 'get_current_screen' ) ? get_current_screen() : null;

		return $screen && '' !== self::$hook_suffix && self::$hook_suffix === $screen->id;
	}

	/**
	 * "Overview" link on the plugin's row on the Plugins screen.
	 *
	 * @param array $links Action links.
	 * @return array
	 */
	public static function action_links( $links ) {
		if ( current_user_can( self::CAPABILITY ) ) {
			array_unshift(
				$links,
				'<a href="' . esc_url( self::url() ) . '">' . esc_html__( 'Overview', 'drift-sunset' ) . '</a>'
			);
		}

		return $links;
	}

	/**
	 * Styles for the screen, on this screen only.
	 *
	 * @param string $hook_suffix Current admin page.
	 * @return void
	 */
	public static function assets( $hook_suffix ) {
		if ( '' === self::$hook_suffix || self::$hook_suffix !== $hook_suffix ) {
			return;
		}

		wp_enqueue_style( 'drift-sunset-admin', DRIFT_SUNSET_URL . 'assets/admin.css', array(), DRIFT_SUNSET_VERSION );
	}

	/**
	 * Copyright and company details in the admin footer, on this screen only.
	 *
	 * @param string $text Footer text.
	 * @return string
	 */
	public static function footer_text( $text ) {
		if ( ! self::is_screen() ) {
			return $text;
		}

		return 'Copyright &copy; ' . esc_html( wp_date( 'Y' ) ) . ' Drift Creative Systems Ltd &middot; Registered in England and Wales, company number 17548220. Registered office: 167-169 Great Portland Street, 5th Floor, London W1W 5PF';
	}

	/**
	 * Drift monogram. Fixed markup; only the fill is variable.
	 *
	 * @param string $fill Fill colour.
	 * @param int    $size Width and height in px.
	 * @return string
	 */
	private static function mark_svg( $fill = 'currentColor', $size = 32 ) {
		$paths = array(
			'M17 12H54C77 12 91 29 91 50C91 72 76 88 54 88H45L55 76H54C70 76 79 64 79 50C79 35 69 24 54 24H29V44H17Z',
			'M17 55H29L17 67Z',
			'M41 55H54L25 88H12Z',
			'M55 76H70L59 88H43Z',
		);

		$svg = sprintf( '<svg xmlns="http://www.w3.org/2000/svg" width="%1$d" height="%1$d" viewBox="0 0 100 100" aria-hidden="true" focusable="false">', (int) $size );
		foreach ( $paths as $d ) {
			$svg .= '<path fill="' . esc_attr( $fill ) . '" d="' . esc_attr( $d ) . '"/>';
		}

		return $svg . '</svg>';
	}

	/**
	 * Print the screen: title, tabs, then the current tab.
	 *
	 * @return void
	 */
	public static function render() {
		if ( ! current_user_can( self::CAPABILITY ) ) {
			return;
		}

		$tabs    = self::tabs();
		$current = isset( $_GET['tab'] ) ? sanitize_key( wp_unslash( $_GET['tab'] ) ) : 'overview'; // phpcs:ignore WordPress.Security.NonceVerification.Recommended -- Tab switch only.
		if ( ! isset( $tabs[ $current ] ) ) {
			$current = 'overview';
		}
		?>
		<div class="wrap drift-sunset">
			<h1 class="drift-sunset__title">
				<span class="drift-sunset__mark"><?php echo self::mark_svg(); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- Fixed markup, attributes escaped in mark_svg(). ?></span>
				<?php echo esc_html( get_admin_page_title() ); ?>
			</h1>

			<?php
			// Pages outside the Settings menu don't print the "Settings saved." notice themselves.
			settings_errors();
			?>

			<?php if ( count( $tabs ) > 1 ) : ?>
				<nav class="nav-tab-wrapper drift-sunset__tabs" aria-label="<?php esc_attr_e( 'Drift: Sunset sections', 'drift-sunset' ); ?>">
					<?php foreach ( $tabs as $key => $tab ) : ?>
						<a href="<?php echo esc_url( self::url( 'overview' === $key ? '' : $key ) ); ?>" class="nav-tab<?php echo $key === $current ? ' nav-tab-active' : ''; ?>"<?php echo $key === $current ? ' aria-current="page"' : ''; ?>><?php echo esc_html( $tab['label'] ); ?></a>
					<?php endforeach; ?>
				</nav>
			<?php endif; ?>

			<?php call_user_func( $tabs[ $current ]['render'] ); ?>
		</div>
		<?php
	}

	/**
	 * Print the Overview tab: cron warnings, upcoming expiries, recently
	 * expired, then developer notes.
	 *
	 * @return void
	 */
	public static function render_overview() {
		self::render_cron_notes();

		$upcoming = Drift_Sunset_Expiry::upcoming( self::UPCOMING_LIMIT );
		$expired  = Drift_Sunset_Expiry::recently_expired( self::EXPIRED_LIMIT );
		?>
		<p class="drift-sunset__lead">
			<?php esc_html_e( 'Posts with an expiry date switch to Draft when it passes. Set the date in the Post Expiry box on any edit screen.', 'drift-sunset' ); ?>
		</p>

		<section class="drift-sunset__card" aria-labelledby="drift-sunset-upcoming-title">
			<h2 class="drift-sunset__card-title" id="drift-sunset-upcoming-title"><?php esc_html_e( 'Upcoming expiries', 'drift-sunset' ); ?></h2>
			<p class="drift-sunset__intro">
				<?php
				printf(
					/* translators: 1: number of posts, 2: site timezone */
					esc_html__( 'Soonest first, up to %1$d posts. Times are in the site timezone (%2$s).', 'drift-sunset' ),
					(int) self::UPCOMING_LIMIT,
					esc_html( wp_timezone_string() )
				);
				?>
			</p>

			<?php if ( $upcoming ) : ?>
				<table class="widefat striped drift-sunset-table">
					<thead>
						<tr>
							<th scope="col"><?php esc_html_e( 'Title', 'drift-sunset' ); ?></th>
							<th scope="col"><?php esc_html_e( 'Type', 'drift-sunset' ); ?></th>
							<th scope="col"><?php esc_html_e( 'Status', 'drift-sunset' ); ?></th>
							<th scope="col"><?php esc_html_e( 'Expires', 'drift-sunset' ); ?></th>
						</tr>
					</thead>
					<tbody>
						<?php foreach ( $upcoming as $post ) : ?>
							<?php $ts = (int) get_post_meta( $post->ID, Drift_Sunset_Expiry::META, true ); ?>
							<tr>
								<td><?php self::render_title( $post ); ?></td>
								<td><?php echo esc_html( self::type_label( $post ) ); ?></td>
								<td><?php echo esc_html( self::status_label( $post ) ); ?></td>
								<td>
									<?php echo esc_html( wp_date( 'd/m/Y H:i', $ts ) ); ?>
									<br>
									<?php if ( $ts > time() ) : ?>
										<span class="description">
											<?php
											/* translators: %s: human-readable time difference, e.g. "3 days" */
											echo esc_html( sprintf( __( 'in %s', 'drift-sunset' ), human_time_diff( time(), $ts ) ) );
											?>
										</span>
									<?php else : ?>
										<span class="drift-sunset-badge drift-sunset-badge--warning"><?php esc_html_e( 'Due on next cron run', 'drift-sunset' ); ?></span>
									<?php endif; ?>
								</td>
							</tr>
						<?php endforeach; ?>
					</tbody>
				</table>
			<?php else : ?>
				<p><?php esc_html_e( 'No posts have an expiry date set.', 'drift-sunset' ); ?></p>
			<?php endif; ?>
		</section>

		<section class="drift-sunset__card" aria-labelledby="drift-sunset-expired-title">
			<h2 class="drift-sunset__card-title" id="drift-sunset-expired-title"><?php esc_html_e( 'Recently expired', 'drift-sunset' ); ?></h2>
			<p class="drift-sunset__intro">
				<?php
				printf(
					/* translators: %d: number of posts */
					esc_html__( 'The last %d posts to expire. Setting a new expiry date on a post removes it from this list.', 'drift-sunset' ),
					(int) self::EXPIRED_LIMIT
				);
				?>
			</p>

			<?php if ( $expired ) : ?>
				<table class="widefat striped drift-sunset-table">
					<thead>
						<tr>
							<th scope="col"><?php esc_html_e( 'Title', 'drift-sunset' ); ?></th>
							<th scope="col"><?php esc_html_e( 'Type', 'drift-sunset' ); ?></th>
							<th scope="col"><?php esc_html_e( 'Status now', 'drift-sunset' ); ?></th>
							<th scope="col"><?php esc_html_e( 'Expired', 'drift-sunset' ); ?></th>
						</tr>
					</thead>
					<tbody>
						<?php foreach ( $expired as $post ) : ?>
							<tr>
								<td><?php self::render_title( $post ); ?></td>
								<td><?php echo esc_html( self::type_label( $post ) ); ?></td>
								<td><?php echo esc_html( self::status_label( $post ) ); ?></td>
								<td><?php echo esc_html( wp_date( 'd/m/Y H:i', (int) get_post_meta( $post->ID, Drift_Sunset_Expiry::META_DONE, true ) ) ); ?></td>
							</tr>
						<?php endforeach; ?>
					</tbody>
				</table>
			<?php else : ?>
				<p><?php esc_html_e( 'Nothing has expired yet.', 'drift-sunset' ); ?></p>
			<?php endif; ?>
		</section>

		<div class="drift-sunset__help">
			<p>
				<?php
				printf(
					/* translators: %s: default time, e.g. 23:59 */
					esc_html__( 'Published, scheduled and private posts switch to Draft at the chosen time. If no time is given it defaults to %s. Each expiry is a single WP-Cron event, with an hourly check as a safety net.', 'drift-sunset' ),
					'<code>' . esc_html( Drift_Sunset_Expiry::DEFAULT_TIME ) . '</code>'
				);
				?>
			</p>
			<p>
				<?php
				printf(
					/* translators: 1: filter name, 2: filter name, 3: action name */
					esc_html__( 'Developers: limit post types with the %1$s filter and required categories with %2$s. Hook into %3$s to send notifications or purge caches when a post expires.', 'drift-sunset' ),
					'<code>drift_sunset_post_types</code>',
					'<code>drift_sunset_required_categories</code>',
					'<code>drift_sunset_post_expired</code>'
				);
				?>
			</p>
		</div>
		<?php
	}

	/**
	 * Cron health, judged by whether the hourly sweep is running on time.
	 *
	 * Each sweep run reschedules the next one an hour ahead, so a sweep that
	 * is well overdue means nothing is calling wp-cron.php. That works the
	 * same whether WP-Cron runs on page loads or from a server cron
	 * (DISABLE_WP_CRON), which the plugin can't see directly.
	 *
	 * @return void
	 */
	private static function render_cron_notes() {
		$next_sweep  = wp_next_scheduled( Drift_Sunset_Expiry::SWEEP );
		$server_cron = defined( 'DISABLE_WP_CRON' ) && DISABLE_WP_CRON;

		if ( ! $next_sweep ) {
			echo '<div class="notice notice-warning inline"><p>' . esc_html__( 'The hourly expiry check isn\'t scheduled. Deactivate and reactivate the plugin to restore it.', 'drift-sunset' ) . '</p></div>';
			return;
		}

		$overdue = time() - $next_sweep;

		if ( $overdue > self::CRON_STALL_AFTER ) {
			echo '<div class="notice notice-warning inline"><p>';
			printf(
				/* translators: %s: human-readable time difference, e.g. "3 hours" */
				esc_html__( 'Cron doesn\'t seem to be running: the hourly expiry check is %s overdue, so posts aren\'t expiring on time.', 'drift-sunset' ),
				esc_html( human_time_diff( $next_sweep, time() ) )
			);
			echo ' ';
			if ( $server_cron ) {
				printf(
					/* translators: 1: DISABLE_WP_CRON, 2: wp-cron.php */
					esc_html__( 'WP-Cron is turned off in wp-config.php (%1$s), so the host\'s server cron must call %2$s. Check it\'s running. If it calls the URL over HTTP, make sure a firewall (WAF) or cache isn\'t blocking or caching that URL.', 'drift-sunset' ),
					'<code>DISABLE_WP_CRON</code>',
					'<code>wp-cron.php</code>'
				);
			} else {
				printf(
					/* translators: %s: wp-cron.php */
					esc_html__( 'WP-Cron runs when someone visits the site, so a quiet site can fall behind. Reload this page in a minute. If it\'s still overdue, check a firewall (WAF), cache or security plugin isn\'t blocking %s.', 'drift-sunset' ),
					'<code>wp-cron.php</code>'
				);
			}
			echo '</p></div>';
			return;
		}

		if ( $server_cron ) {
			// Reassure rather than warn: hosts that run a server cron set
			// DISABLE_WP_CRON as standard, and the sweep shows it's working.
			echo '<div class="notice notice-info inline"><p>';
			printf(
				/* translators: %s: DISABLE_WP_CRON */
				esc_html__( 'WP-Cron is turned off in wp-config.php (%s), so the host\'s server cron runs it instead. The hourly expiry check is on schedule, so that looks to be working.', 'drift-sunset' ),
				'<code>DISABLE_WP_CRON</code>'
			);
			echo '</p></div>';
		}
	}

	/**
	 * Post title, linked to its edit screen when the user can edit it.
	 *
	 * @param WP_Post $post Post.
	 * @return void
	 */
	private static function render_title( $post ) {
		$title = get_the_title( $post );
		if ( '' === trim( wp_strip_all_tags( $title ) ) ) {
			$title = __( '(no title)', 'drift-sunset' );
		}

		$link = current_user_can( 'edit_post', $post->ID ) ? get_edit_post_link( $post->ID ) : '';

		if ( $link ) {
			echo '<a href="' . esc_url( $link ) . '"><strong>' . esc_html( $title ) . '</strong></a>';
		} else {
			echo '<strong>' . esc_html( $title ) . '</strong>';
		}
	}

	/**
	 * Singular post type label, e.g. "Page".
	 *
	 * @param WP_Post $post Post.
	 * @return string
	 */
	private static function type_label( $post ) {
		$object = get_post_type_object( $post->post_type );
		return $object ? $object->labels->singular_name : $post->post_type;
	}

	/**
	 * Post status label, e.g. "Published".
	 *
	 * @param WP_Post $post Post.
	 * @return string
	 */
	private static function status_label( $post ) {
		$object = get_post_status_object( $post->post_status );
		return $object ? $object->label : $post->post_status;
	}
}
