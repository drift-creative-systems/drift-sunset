/**
 * Post Expiry box on the edit screen.
 *
 * - Picking a date with no time fills in the default time (DRIFT_SUNSET.defaultTime).
 * - "Clear expiry" empties both fields; saving the post then removes the expiry.
 * - Classic editor: if the post is in a required category (DRIFT_SUNSET.required)
 *   and has no date, Publish / Schedule / Update is stopped with a message.
 *   Save Draft still works. The block editor's check is in admin-block.js.
 *
 * Delegated from document because the block editor moves meta boxes around
 * the DOM after load.
 */
(function ($) {
	'use strict';

	var config      = window.DRIFT_SUNSET || {};
	var defaultTime = config.defaultTime || '23:59';
	var required    = $.map( config.required || [], function ( id ) {
		return String( id );
	} );

	$(document).on('change.drift_sunset', '#drift_sunset_date', function () {
		var $time = $('#drift_sunset_time');

		if ( $(this).val() && ! $time.val() ) {
			$time.val(defaultTime);
		}

		drift_sunset_hide_error();
	});

	$(document).on('click.drift_sunset', '#drift_sunset_clear', function (event) {
		event.preventDefault();
		$('#drift_sunset_date, #drift_sunset_time').val('');
		$('#drift_sunset_date').trigger('focus');
	});

	/**
	 * Whether any ticked category in the classic editor's checklist is required.
	 *
	 * @return {boolean}
	 */
	function drift_sunset_in_required_category() {
		var found = false;

		$('#categorychecklist input[type="checkbox"]:checked').each(function () {
			if ( -1 !== $.inArray( String( $(this).val() ), required ) ) {
				found = true;
				return false;
			}
		});

		return found;
	}

	function drift_sunset_hide_error() {
		$('.drift-sunset-box__error').prop('hidden', true).text('');
	}

	// Classic editor only: #publish is Publish, Schedule or Update there.
	if ( required.length ) {
		$(document).on('click.drift_sunset', '#post #publish', function (event) {
			if ( $('#drift_sunset_date').val() || ! drift_sunset_in_required_category() ) {
				return;
			}

			event.preventDefault();
			$('.drift-sunset-box__error').text(config.requiredMessage).prop('hidden', false);
			$('#drift_sunset_date').trigger('focus');
		});
	}
})(jQuery);
