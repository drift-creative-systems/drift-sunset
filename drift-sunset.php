<?php
/**
 * Plugin Name:       Drift: Sunset
 * Plugin URI:        https://driftcreativesystems.co.uk/
 * Description:       Set an expiry date and time on any post or page. When it passes, the post is switched to Draft. Upcoming and recent expiries are listed under Tools → Drift: Sunset.
 * Version:           1.0.0
 * Requires at least: 6.0
 * Requires PHP:      7.4
 * Author:            Drift Creative Systems
 * Author URI:        https://driftcreativesystems.co.uk/
 * License:           GPL-2.0-or-later
 * License URI:       https://www.gnu.org/licenses/gpl-2.0.html
 * Text Domain:       drift-sunset
 *
 * Copyright © 2026 Drift Creative Systems Ltd
 *
 * @package Drift_Sunset
 */

defined( 'ABSPATH' ) || exit;

/*
|--------------------------------------------------------------------------
| Duplicate install guard
|--------------------------------------------------------------------------
| A second copy (e.g. a GitHub "Source code" zip unpacked alongside the real
| folder) would fatal on redeclared classes. Bail and tell the admin instead.
*/
if ( defined( 'DRIFT_SUNSET_VERSION' ) ) {
	add_action(
		'admin_notices',
		function () {
			echo '<div class="notice notice-error"><p>' . esc_html__( 'Drift: Sunset is installed more than once. Please delete the duplicate plugin folder.', 'drift-sunset' ) . '</p></div>';
		}
	);
	return;
}

define( 'DRIFT_SUNSET_VERSION', '1.0.0' );
define( 'DRIFT_SUNSET_FILE', __FILE__ );
define( 'DRIFT_SUNSET_PATH', plugin_dir_path( __FILE__ ) );
define( 'DRIFT_SUNSET_URL', plugin_dir_url( __FILE__ ) );

require_once DRIFT_SUNSET_PATH . 'includes/class-drift-sunset-expiry.php';
require_once DRIFT_SUNSET_PATH . 'includes/class-drift-sunset-settings.php';
require_once DRIFT_SUNSET_PATH . 'includes/class-drift-sunset-required.php';
require_once DRIFT_SUNSET_PATH . 'includes/class-drift-sunset-admin.php';

// Cron callbacks must be hooked on every request, including wp-cron.php, so
// the expiry class hooks in straight away rather than waiting for admin.
Drift_Sunset_Expiry::init();

add_action(
	'plugins_loaded',
	function () {
		Drift_Sunset_Settings::init();
		Drift_Sunset_Required::init();
		Drift_Sunset_Admin::init();
	}
);

register_activation_hook( __FILE__, array( 'Drift_Sunset_Expiry', 'activate' ) );
register_deactivation_hook( __FILE__, array( 'Drift_Sunset_Expiry', 'deactivate' ) );
