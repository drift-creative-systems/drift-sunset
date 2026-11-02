<?php
/**
 * Expiry: the edit-screen box, saving and scheduling, the cron callbacks
 * that switch posts to Draft, and the "Expires" list column.
 *
 * Meta keys and cron hook names are stored data: never rename them, or
 * expiries already scheduled on live sites stop firing.
 *
 * @package Drift_Sunset
 */

defined( 'ABSPATH' ) || exit;

/**
 * Post expiry.
 */
final class Drift_Sunset_Expiry {

	const META         = '_drift_sunset_expires';     // UTC unix timestamp.
	const META_DONE    = '_drift_sunset_expired_at';  // When it was actually expired.
	const HOOK         = 'drift_sunset_expire_post';  // Single event per post.
	const SWEEP        = 'drift_sunset_sweep';        // Hourly safety net.
	const NONCE        = 'drift_sunset_nonce';
	const VERSION_OPT  = 'drift_sunset_version';      // Last version that ran maybe_upgrade().
	const DEFAULT_TIME = '23:59';                     // "Midnight" of the chosen day. Change to '00:00' for start of day.

	/** Statuses that get knocked back to draft. */
	const EXPIRABLE = array( 'publish', 'future', 'private' );

	/**
	 * Hook in. Runs on every request, as the cron callbacks must be registered
	 * when wp-cron.php fires them.
	 *
	 * @return void
	 */
	public static function init() {
		add_action( 'init', array( __CLASS__, 'maybe_upgrade' ) );
		add_action( 'add_meta_boxes', array( __CLASS__, 'add_meta_box' ) );
		add_action( 'save_post', array( __CLASS__, 'save' ), 10, 2 );
		add_action( self::HOOK, array( __CLASS__, 'expire' ) );
		add_action( self::SWEEP, array( __CLASS__, 'sweep' ) );
		add_action( 'before_delete_post', array( __CLASS__, 'unschedule' ) );
		add_action( 'admin_init', array( __CLASS__, 'register_columns' ) );
		add_action( 'admin_enqueue_scripts', array( __CLASS__, 'assets' ) );
	}

	/* -------------------------------------------------------------------------
	 * Post types
	 * ---------------------------------------------------------------------- */

	/**
	 * Post types that get the expiry box and column.
	 *
	 * @return string[]
	 */
	public static function post_types() {
		$types = get_post_types( array( 'public' => true ), 'names' );
		unset( $types['attachment'] );

		/**
		 * Filters which post types get the expiry box.
		 * add_filter( 'drift_sunset_post_types', fn() => [ 'post', 'event' ] );
		 *
		 * @param string[] $types Public post types except attachments.
		 */
		return (array) apply_filters( 'drift_sunset_post_types', array_values( $types ) );
	}

	/* -------------------------------------------------------------------------
	 * Assets
	 * ---------------------------------------------------------------------- */

	/**
	 * Edit screen gets the box script and styles; list screens get the styles
	 * for the "Expired" column label.
	 *
	 * @param string $hook Current admin page hook suffix.
	 * @return void
	 */
	public static function assets( $hook ) {
		if ( ! in_array( $hook, array( 'post.php', 'post-new.php', 'edit.php' ), true ) ) {
			return;
		}

		$screen = get_current_screen();
		if ( ! $screen || ! in_array( $screen->post_type, self::post_types(), true ) ) {
			return;
		}

		wp_enqueue_style( 'drift-sunset-admin', DRIFT_SUNSET_URL . 'assets/admin.css', array(), DRIFT_SUNSET_VERSION );

		if ( 'edit.php' === $hook ) {
			return;
		}

		$required = Drift_Sunset_Required::categories_for_type( $screen->post_type );

		wp_enqueue_script( 'drift-sunset-admin', DRIFT_SUNSET_URL . 'assets/admin.js', array( 'jquery' ), DRIFT_SUNSET_VERSION, true );
		wp_localize_script(
			'drift-sunset-admin',
			'DRIFT_SUNSET',
			array(
				'defaultTime'     => self::DEFAULT_TIME,
				'required'        => $required,
				'requiredMessage' => $required ? Drift_Sunset_Required::message() : '',
			)
		);

		// The block editor only exposes categories and save locking through
		// wp.data, so its required-expiry check lives in its own script.
		if ( $required && method_exists( $screen, 'is_block_editor' ) && $screen->is_block_editor() ) {
			wp_enqueue_script( 'drift-sunset-admin-block', DRIFT_SUNSET_URL . 'assets/admin-block.js', array( 'drift-sunset-admin', 'wp-data', 'wp-hooks', 'wp-editor', 'wp-notices' ), DRIFT_SUNSET_VERSION, true );
		}
	}

	/* -------------------------------------------------------------------------
	 * Meta box
	 * ---------------------------------------------------------------------- */

	/**
	 * Register the sidebar box on every supported post type.
	 *
	 * @return void
	 */
	public static function add_meta_box() {
		add_meta_box(
			'drift_sunset_expiry',
			__( 'Post Expiry', 'drift-sunset' ),
			array( __CLASS__, 'render' ),
			self::post_types(),
			'side',
			'high'
		);
	}

	/**
	 * Print the date/time fields, plus a note when the post type has required
	 * categories. Behaviour (default time, clear button, required check) is
	 * in assets/admin.js and assets/admin-block.js.
	 *
	 * @param WP_Post $post Post being edited.
	 * @return void
	 */
	public static function render( $post ) {
		$ts   = (int) get_post_meta( $post->ID, self::META, true );
		$done = (int) get_post_meta( $post->ID, self::META_DONE, true );
		$date = '';
		$time = '';

		if ( $ts ) {
			$dt   = ( new DateTimeImmutable( '@' . $ts ) )->setTimezone( wp_timezone() );
			$date = $dt->format( 'Y-m-d' );
			$time = $dt->format( 'H:i' );
		}

		wp_nonce_field( 'drift_sunset_save', self::NONCE );
		?>
		<div class="drift-sunset-box">
			<p class="drift-sunset-box__field">
				<label for="drift_sunset_date"><strong><?php esc_html_e( 'Expiry date', 'drift-sunset' ); ?></strong></label>
				<input type="date" id="drift_sunset_date" name="drift_sunset_date" value="<?php echo esc_attr( $date ); ?>">
			</p>
			<p class="drift-sunset-box__field">
				<label for="drift_sunset_time"><strong><?php esc_html_e( 'Time', 'drift-sunset' ); ?></strong></label>
				<input type="time" id="drift_sunset_time" name="drift_sunset_time" value="<?php echo esc_attr( $time ); ?>">
			</p>
			<?php if ( Drift_Sunset_Required::categories_for_type( $post->post_type ) ) : ?>
				<p class="description drift-sunset-box__required">
					<?php
					printf(
						/* translators: %s: category names, e.g. "Events, Courses" */
						esc_html__( 'Required before publishing for posts in: %s.', 'drift-sunset' ),
						esc_html( Drift_Sunset_Required::category_names() )
					);
					?>
				</p>
				<p class="drift-sunset-box__error" role="alert" hidden></p>
			<?php endif; ?>
			<p>
				<button type="button" class="button-link" id="drift_sunset_clear"><?php esc_html_e( 'Clear expiry', 'drift-sunset' ); ?></button>
			</p>
			<p class="description">
				<?php
				printf(
					/* translators: %s: site timezone */
					esc_html__( 'Post switches to Draft when this passes. Time defaults to midnight if left blank. Timezone: %s', 'drift-sunset' ),
					esc_html( wp_timezone_string() )
				);
				?>
			</p>
			<?php if ( $done && ! $ts ) : ?>
				<p class="description drift-sunset-expired">
					<?php
					printf(
						/* translators: %s: date expired */
						esc_html__( 'Expired on %s', 'drift-sunset' ),
						esc_html( wp_date( 'd/m/Y H:i', $done ) )
					);
					?>
				</p>
			<?php endif; ?>
		</div>
		<?php
	}

	/* -------------------------------------------------------------------------
	 * Save + scheduling
	 * ---------------------------------------------------------------------- */

	/**
	 * Save the expiry and (re)schedule its single cron event.
	 *
	 * @param int     $post_id Post ID.
	 * @param WP_Post $post    Post object.
	 * @return void
	 */
	public static function save( $post_id, $post ) { // phpcs:ignore Generic.CodeAnalysis.UnusedFunctionParameter.FoundAfterLastUsed -- Signature set by save_post.
		if ( ! self::box_submitted() ) {
			return; // Also bails for quick edit, REST-only saves and our own cron update.
		}
		if ( ( defined( 'DOING_AUTOSAVE' ) && DOING_AUTOSAVE ) || wp_is_post_revision( $post_id ) ) {
			return;
		}
		if ( ! current_user_can( 'edit_post', $post_id ) ) {
			return;
		}

		self::unschedule( $post_id );

		$ts = self::posted_timestamp();
		if ( ! $ts ) {
			delete_post_meta( $post_id, self::META );
			return;
		}

		update_post_meta( $post_id, self::META, $ts );
		delete_post_meta( $post_id, self::META_DONE );

		// Past dates fire on the next cron run.
		wp_schedule_single_event( max( $ts, time() ), self::HOOK, array( (int) $post_id ) );
	}

	/**
	 * Whether this request is a save from the edit screen with our box on it
	 * (valid nonce).
	 *
	 * @return bool
	 */
	public static function box_submitted() {
		return isset( $_POST[ self::NONCE ] ) && wp_verify_nonce( sanitize_text_field( wp_unslash( $_POST[ self::NONCE ] ) ), 'drift_sunset_save' );
	}

	/**
	 * The expiry submitted in the box as a UTC timestamp, or 0 if the date is
	 * empty or invalid. A missing or invalid time falls back to DEFAULT_TIME.
	 * Callers check box_submitted() first.
	 *
	 * @return int
	 */
	public static function posted_timestamp() {
		// phpcs:disable WordPress.Security.NonceVerification.Missing -- Callers verify the nonce with box_submitted().
		$date = isset( $_POST['drift_sunset_date'] ) ? sanitize_text_field( wp_unslash( $_POST['drift_sunset_date'] ) ) : '';
		$time = isset( $_POST['drift_sunset_time'] ) ? sanitize_text_field( wp_unslash( $_POST['drift_sunset_time'] ) ) : '';
		// phpcs:enable

		if ( ! preg_match( '/^\d{4}-\d{2}-\d{2}$/', $date ) ) {
			return 0;
		}
		if ( ! preg_match( '/^\d{2}:\d{2}$/', $time ) ) {
			$time = self::DEFAULT_TIME;
		}

		$dt = DateTimeImmutable::createFromFormat( 'Y-m-d H:i', $date . ' ' . $time, wp_timezone() );

		return $dt ? $dt->getTimestamp() : 0;
	}

	/**
	 * Remove a post's queued expiry event.
	 *
	 * @param int $post_id Post ID.
	 * @return void
	 */
	public static function unschedule( $post_id ) {
		wp_clear_scheduled_hook( self::HOOK, array( (int) $post_id ) );
	}

	/* -------------------------------------------------------------------------
	 * Expiry
	 * ---------------------------------------------------------------------- */

	/**
	 * Switch a post to Draft if its expiry has passed. Safe to call more than
	 * once: it re-checks the stored timestamp first.
	 *
	 * @param int $post_id Post ID.
	 * @return void
	 */
	public static function expire( $post_id ) {
		$post = get_post( $post_id );
		if ( ! $post ) {
			return;
		}

		$ts = (int) get_post_meta( $post_id, self::META, true );
		if ( ! $ts || $ts > time() ) {
			return; // Cleared or moved since this event was queued.
		}

		if ( in_array( $post->post_status, self::EXPIRABLE, true ) ) {
			wp_update_post(
				array(
					'ID'          => $post_id,
					'post_status' => 'draft',
				)
			);

			/**
			 * Fires after a post is switched to Draft. For notifications,
			 * webhooks, cache purges etc.
			 *
			 * @param int     $post_id Post ID.
			 * @param WP_Post $post    The post as it was before expiry.
			 */
			do_action( 'drift_sunset_post_expired', $post_id, $post );
		}

		delete_post_meta( $post_id, self::META );
		update_post_meta( $post_id, self::META_DONE, time() );
	}

	/**
	 * Hourly safety net for missed single events (cron hiccups, migrations, restores).
	 *
	 * @return void
	 */
	public static function sweep() {
		$ids = get_posts(
			array(
				'post_type'        => self::post_types(),
				'post_status'      => self::EXPIRABLE,
				'posts_per_page'   => 100,
				'fields'           => 'ids',
				'no_found_rows'    => true,
				'suppress_filters' => true,
				'meta_query'       => array( // phpcs:ignore WordPress.DB.SlowDBQuery.slow_db_query_meta_query -- Hourly cron only.
					array(
						'key'     => self::META,
						'value'   => time(),
						'compare' => '<=',
						'type'    => 'NUMERIC',
					),
				),
			)
		);

		foreach ( $ids as $id ) {
			self::expire( $id );
		}
	}

	/* -------------------------------------------------------------------------
	 * Queries for the Tools → Drift: Sunset overview
	 * ---------------------------------------------------------------------- */

	/**
	 * Posts with an expiry set, soonest first.
	 *
	 * @param int $limit Max posts.
	 * @return WP_Post[]
	 */
	public static function upcoming( $limit = 50 ) {
		return self::query_by_meta( self::META, 'ASC', $limit );
	}

	/**
	 * Posts that have expired, most recent first.
	 *
	 * @param int $limit Max posts.
	 * @return WP_Post[]
	 */
	public static function recently_expired( $limit = 20 ) {
		return self::query_by_meta( self::META_DONE, 'DESC', $limit );
	}

	/**
	 * Posts of any supported type and status (bar trash) that have a meta key,
	 * ordered by its numeric value.
	 *
	 * @param string $key   Meta key.
	 * @param string $order ASC or DESC.
	 * @param int    $limit Max posts.
	 * @return WP_Post[]
	 */
	private static function query_by_meta( $key, $order, $limit ) {
		return get_posts(
			array(
				'post_type'        => self::post_types(),
				'post_status'      => 'any',
				'posts_per_page'   => absint( $limit ),
				'no_found_rows'    => true,
				'suppress_filters' => true,
				'meta_key'         => $key, // phpcs:ignore WordPress.DB.SlowDBQuery.slow_db_query_meta_key -- Admin overview only.
				'orderby'          => 'meta_value_num',
				'order'            => 'DESC' === $order ? 'DESC' : 'ASC',
			)
		);
	}

	/* -------------------------------------------------------------------------
	 * Admin list column
	 * ---------------------------------------------------------------------- */

	/**
	 * Add the "Expires" column to every supported post type's list screen.
	 *
	 * @return void
	 */
	public static function register_columns() {
		foreach ( self::post_types() as $type ) {
			add_filter( "manage_{$type}_posts_columns", array( __CLASS__, 'add_column' ) );
			add_action( "manage_{$type}_posts_custom_column", array( __CLASS__, 'column' ), 10, 2 );
		}
	}

	/**
	 * Column header.
	 *
	 * @param array $cols Columns.
	 * @return array
	 */
	public static function add_column( $cols ) {
		$cols['drift_sunset_expiry'] = __( 'Expires', 'drift-sunset' );
		return $cols;
	}

	/**
	 * Column cell: expiry date, "Expired" date, or a dash.
	 *
	 * @param string $col     Column key.
	 * @param int    $post_id Post ID.
	 * @return void
	 */
	public static function column( $col, $post_id ) {
		if ( 'drift_sunset_expiry' !== $col ) {
			return;
		}
		$ts   = (int) get_post_meta( $post_id, self::META, true );
		$done = (int) get_post_meta( $post_id, self::META_DONE, true );

		if ( $ts ) {
			echo esc_html( wp_date( 'd/m/Y H:i', $ts ) );
		} elseif ( $done ) {
			echo '<span class="drift-sunset-expired">' . esc_html__( 'Expired', 'drift-sunset' ) . ' ' . esc_html( wp_date( 'd/m/Y', $done ) ) . '</span>';
		} else {
			echo '&mdash;';
		}
	}

	/* -------------------------------------------------------------------------
	 * Activation and upgrades
	 * ---------------------------------------------------------------------- */

	/**
	 * Make sure the hourly sweep is queued.
	 *
	 * @return void
	 */
	public static function schedule_sweep() {
		if ( ! wp_next_scheduled( self::SWEEP ) ) {
			wp_schedule_event( time() + 300, 'hourly', self::SWEEP );
		}
	}

	/**
	 * Activation hook.
	 *
	 * @return void
	 */
	public static function activate() {
		self::schedule_sweep();
		Drift_Sunset_Settings::install();
		update_option( self::VERSION_OPT, DRIFT_SUNSET_VERSION );
	}

	/**
	 * Updates from the Plugins screen don't re-run the activation hook, so
	 * re-check the sweep and the settings option once per new version. One
	 * autoloaded option read otherwise.
	 *
	 * @return void
	 */
	public static function maybe_upgrade() {
		if ( DRIFT_SUNSET_VERSION === get_option( self::VERSION_OPT ) ) {
			return;
		}

		self::schedule_sweep();
		Drift_Sunset_Settings::install();
		update_option( self::VERSION_OPT, DRIFT_SUNSET_VERSION );
	}

	/**
	 * Deactivation hook: clear the sweep and every queued expiry. Expiry dates
	 * stay in post meta; on reactivation the sweep catches anything overdue.
	 *
	 * @return void
	 */
	public static function deactivate() {
		wp_clear_scheduled_hook( self::SWEEP );
		wp_unschedule_hook( self::HOOK );
		delete_option( self::VERSION_OPT );
	}
}
