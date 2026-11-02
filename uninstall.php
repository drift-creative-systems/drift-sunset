<?php
/**
 * Removes the plugin's options, expiry dates and queued events when it is deleted.
 *
 * @package Drift_Sunset
 */

defined( 'WP_UNINSTALL_PLUGIN' ) || exit;

/**
 * Removes everything the plugin stored on the current site.
 *
 * @return void
 */
function drift_sunset_uninstall_site() {
	delete_option( 'drift_sunset_settings' );
	delete_option( 'drift_sunset_version' );

	delete_post_meta_by_key( '_drift_sunset_expires' );
	delete_post_meta_by_key( '_drift_sunset_expired_at' );

	// Deactivation already clears these; this covers a deactivation that failed part way.
	wp_clear_scheduled_hook( 'drift_sunset_sweep' );
	wp_unschedule_hook( 'drift_sunset_expire_post' );
}

if ( is_multisite() ) {
	$drift_sunset_site_ids = get_sites(
		array(
			'fields' => 'ids',
			'number' => 0,
		)
	);

	foreach ( $drift_sunset_site_ids as $drift_sunset_site_id ) {
		switch_to_blog( $drift_sunset_site_id );
		drift_sunset_uninstall_site();
		restore_current_blog();
	}
} else {
	drift_sunset_uninstall_site();
}
