/**
 * Required expiry in the block editor.
 *
 * Loaded only when the post type has required categories. When the post is
 * in one of them and the Post Expiry box has no date:
 *
 * - a warning notice shows at the top of the editor;
 * - saving is locked while the pre-publish panel is open (so Publish /
 *   Schedule is disabled) and for posts that are already live or scheduled
 *   (so Update is disabled). Drafts can still be saved;
 * - where the editor supports the editor.preSavePost filter (recent WP
 *   versions), a publish is also refused when pre-publish checks are off.
 *
 * Categories and save locking only exist in wp.data, hence the wp globals;
 * the date is read from the meta box field with jQuery.
 */
(function ($, wp) {
	'use strict';

	var config   = window.DRIFT_SUNSET || {};
	var required = $.map( config.required || [], function ( id ) {
		return Number( id );
	} );

	if ( ! required.length || ! wp || ! wp.data ) {
		return;
	}

	var LOCK_KEY  = 'drift-sunset-required';
	var NOTICE_ID = 'drift-sunset-required';
	var LIVE      = [ 'publish', 'future', 'private' ];

	var locked      = false;
	var noticeShown = false;

	function drift_sunset_editor() {
		return wp.data.select( 'core/editor' );
	}

	/**
	 * In a required category with no date in the box.
	 *
	 * @return {boolean}
	 */
	function drift_sunset_missing_date() {
		var cats = drift_sunset_editor().getEditedPostAttribute( 'categories' ) || [];
		var hit  = false;

		$.each( cats, function ( i, id ) {
			if ( -1 !== $.inArray( Number( id ), required ) ) {
				hit = true;
				return false;
			}
		} );

		return hit && ! $( '#drift_sunset_date' ).val();
	}

	/**
	 * Pre-publish panel open. Moved from core/edit-post to core/editor in WP 6.6.
	 *
	 * @return {boolean}
	 */
	function drift_sunset_publish_panel_open() {
		var editor   = drift_sunset_editor();
		var editPost = wp.data.select( 'core/edit-post' );

		if ( editor.isPublishSidebarOpened ) {
			return editor.isPublishSidebarOpened();
		}

		return !! ( editPost && editPost.isPublishSidebarOpened && editPost.isPublishSidebarOpened() );
	}

	/**
	 * Apply the lock and notice. Called on every store change, so it only
	 * dispatches when something actually changes.
	 */
	function drift_sunset_sync() {
		var editor = drift_sunset_editor();

		if ( ! editor || ! editor.getCurrentPostId() ) {
			return;
		}

		var missing = drift_sunset_missing_date();
		var live    = -1 !== $.inArray( editor.getCurrentPostAttribute( 'status' ), LIVE );
		var lock    = missing && ( live || drift_sunset_publish_panel_open() );

		if ( lock !== locked ) {
			locked = lock;
			wp.data.dispatch( 'core/editor' )[ lock ? 'lockPostSaving' : 'unlockPostSaving' ]( LOCK_KEY );
		}

		if ( missing !== noticeShown ) {
			noticeShown = missing;

			if ( missing ) {
				wp.data.dispatch( 'core/notices' ).createWarningNotice( config.requiredMessage, {
					id: NOTICE_ID,
					isDismissible: false
				} );
			} else {
				wp.data.dispatch( 'core/notices' ).removeNotice( NOTICE_ID );
			}
		}
	}

	wp.data.subscribe( drift_sunset_sync );

	// The date field isn't in a store, so re-check when it changes. The
	// "Clear expiry" handler in admin.js runs first (it's bound first).
	$( document ).on( 'change.drift_sunset input.drift_sunset', '#drift_sunset_date', drift_sunset_sync );
	$( document ).on( 'click.drift_sunset', '#drift_sunset_clear', drift_sunset_sync );

	// Covers publishing with pre-publish checks turned off. Ignored by
	// editors that don't run this filter.
	if ( wp.hooks && wp.hooks.addFilter && window.Promise ) {
		wp.hooks.addFilter( 'editor.preSavePost', 'drift-sunset/required', function ( edits, options ) {
			if ( options && options.isAutosave ) {
				return edits;
			}

			var status = ( edits && edits.status ) || drift_sunset_editor().getEditedPostAttribute( 'status' );

			if ( -1 !== $.inArray( status, LIVE ) && drift_sunset_missing_date() ) {
				return Promise.reject( new Error( config.requiredMessage ) );
			}

			return edits;
		} );
	}
})(jQuery, window.wp);
