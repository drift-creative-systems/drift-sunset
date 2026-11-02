<?php
/**
 * Settings: the Tools → Drift: Sunset → Settings tab.
 *
 * One setting so far: which post categories need an expiry date before a
 * post can be published. Stored with the native Settings API in the
 * non-autoloaded `drift_sunset_settings` option.
 *
 * @package Drift_Sunset
 */

defined( 'ABSPATH' ) || exit;

/**
 * Plugin settings.
 */
final class Drift_Sunset_Settings {

	const OPTION = 'drift_sunset_settings';
	const GROUP  = 'drift_sunset_settings';

	/**
	 * Only admins change settings. Editors keep the overview.
	 */
	const CAPABILITY = 'manage_options';

	/**
	 * Hook in.
	 *
	 * @return void
	 */
	public static function init() {
		add_action( 'admin_init', array( __CLASS__, 'register' ) );
	}

	/**
	 * Register the option with the Settings API. options.php checks
	 * manage_options for this group by default, matching CAPABILITY.
	 *
	 * @return void
	 */
	public static function register() {
		register_setting(
			self::GROUP,
			self::OPTION,
			array(
				'type'              => 'array',
				'sanitize_callback' => array( __CLASS__, 'sanitize' ),
				'default'           => self::defaults(),
				'show_in_rest'      => false,
			)
		);
	}

	/**
	 * Create the option as non-autoloaded, so it isn't loaded on every
	 * request. Does nothing if it already exists.
	 *
	 * @return void
	 */
	public static function install() {
		add_option( self::OPTION, self::defaults(), '', false );
	}

	/**
	 * Default values.
	 *
	 * @return array
	 */
	public static function defaults() {
		return array(
			'required_categories' => array(),
		);
	}

	/**
	 * Saved settings merged over the defaults.
	 *
	 * @return array
	 */
	public static function get() {
		$settings = get_option( self::OPTION, array() );

		return wp_parse_args( is_array( $settings ) ? $settings : array(), self::defaults() );
	}

	/**
	 * Clean the submitted settings. An empty checklist posts nothing, so a
	 * missing key means "none ticked".
	 *
	 * @param mixed $input Submitted value.
	 * @return array
	 */
	public static function sanitize( $input ) {
		$ids = ( is_array( $input ) && isset( $input['required_categories'] ) )
			? array_map( 'absint', (array) $input['required_categories'] )
			: array();

		// Keep only categories that still exist.
		$ids = array_filter(
			array_unique( $ids ),
			function ( $id ) {
				return $id && term_exists( $id, 'category' );
			}
		);

		return array(
			'required_categories' => array_values( $ids ),
		);
	}

	/**
	 * Category term IDs whose posts need an expiry date before publishing.
	 *
	 * @return int[]
	 */
	public static function required_categories() {
		$ids = self::get()['required_categories'];

		/**
		 * Filters the categories that need an expiry date.
		 * add_filter( 'drift_sunset_required_categories', fn( $ids ) => array_merge( $ids, [ 12 ] ) );
		 *
		 * @param int[] $ids Category term IDs ticked on the Settings tab.
		 */
		$ids = (array) apply_filters( 'drift_sunset_required_categories', array_map( 'absint', (array) $ids ) );

		return array_values( array_filter( array_map( 'absint', $ids ) ) );
	}

	/* -------------------------------------------------------------------------
	 * Settings tab
	 * ---------------------------------------------------------------------- */

	/**
	 * Print the Settings tab. Drift_Sunset_Admin prints the page shell and
	 * the "Settings saved." notice.
	 *
	 * @return void
	 */
	public static function render() {
		$terms = get_terms(
			array(
				'taxonomy'   => 'category',
				'hide_empty' => false,
				'orderby'    => 'name',
			)
		);
		$terms = is_wp_error( $terms ) ? array() : $terms;

		// Post types the requirement can apply to: supported and using categories.
		$types = array_filter( Drift_Sunset_Expiry::post_types(), array( 'Drift_Sunset_Required', 'applies_to_type' ) );
		?>
		<section class="drift-sunset__card" aria-labelledby="drift-sunset-required-title">
			<h2 class="drift-sunset__card-title" id="drift-sunset-required-title"><?php esc_html_e( 'Required expiry dates', 'drift-sunset' ); ?></h2>
			<p class="drift-sunset__intro">
				<?php esc_html_e( 'Posts in a ticked category can\'t be published, scheduled or updated until they have an expiry date. Drafts can still be saved without one.', 'drift-sunset' ); ?>
			</p>

			<?php if ( ! $types ) : ?>
				<div class="notice notice-info inline"><p><?php esc_html_e( 'None of the post types with the Post Expiry box use categories, so this setting has no effect.', 'drift-sunset' ); ?></p></div>
			<?php endif; ?>

			<form method="post" action="options.php">
				<?php settings_fields( self::GROUP ); ?>

				<table class="form-table" role="presentation">
					<tr>
						<th scope="row"><?php esc_html_e( 'Categories', 'drift-sunset' ); ?></th>
						<td>
							<?php if ( $terms ) : ?>
								<fieldset>
									<legend class="screen-reader-text"><?php esc_html_e( 'Categories that need an expiry date', 'drift-sunset' ); ?></legend>
									<?php self::render_checklist( $terms, self::get()['required_categories'] ); ?>
								</fieldset>
								<?php if ( $types ) : ?>
									<p class="description">
										<?php
										printf(
											/* translators: %s: post type names, e.g. "Posts, Events" */
											esc_html__( 'Applies to: %s.', 'drift-sunset' ),
											esc_html( implode( ', ', array_map( array( __CLASS__, 'type_name' ), $types ) ) )
										);
										?>
									</p>
								<?php endif; ?>
							<?php else : ?>
								<p><?php esc_html_e( 'There are no categories yet.', 'drift-sunset' ); ?></p>
							<?php endif; ?>
						</td>
					</tr>
				</table>

				<?php submit_button(); ?>
			</form>
		</section>
		<?php
	}

	/**
	 * Print category checkboxes, children indented under their parents.
	 *
	 * @param WP_Term[] $terms    All categories.
	 * @param int[]     $selected Ticked term IDs.
	 * @param int       $parent   Parent term ID for this level.
	 * @param int       $depth    Nesting depth.
	 * @return void
	 */
	private static function render_checklist( $terms, $selected, $parent = 0, $depth = 0 ) {
		foreach ( $terms as $term ) {
			if ( (int) $term->parent !== (int) $parent ) {
				continue;
			}
			?>
			<label class="drift-sunset-cat drift-sunset-cat--depth-<?php echo absint( min( $depth, 3 ) ); ?>">
				<input type="checkbox" name="<?php echo esc_attr( self::OPTION ); ?>[required_categories][]" value="<?php echo absint( $term->term_id ); ?>" <?php checked( in_array( (int) $term->term_id, array_map( 'intval', (array) $selected ), true ) ); ?>>
				<?php echo esc_html( $term->name ); ?>
			</label>
			<?php
			self::render_checklist( $terms, $selected, $term->term_id, $depth + 1 );
		}
	}

	/**
	 * Plural post type label, e.g. "Posts".
	 *
	 * @param string $type Post type name.
	 * @return string
	 */
	private static function type_name( $type ) {
		$object = get_post_type_object( $type );
		return $object ? $object->labels->name : $type;
	}
}
