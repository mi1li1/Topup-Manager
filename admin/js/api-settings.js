/* global jQuery, wctfApiSettings */

(function ($) {
	'use strict';

	var $button = $('#wctf-test-connection');

	if (!$button.length) {
		return;
	}

	var $spinner = $('#wctf-test-connection-spinner');
	var $results = $('#wctf-connection-results');
	var $status = $('#wctf-connection-status');
	var $accountRow = $('#wctf-account-row');
	var $accountName = $('#wctf-account-name');
	var $balanceRow = $('#wctf-balance-row');
	var $balance = $('#wctf-balance');
	var $errorRow = $('#wctf-error-row');
	var $error = $('#wctf-connection-error');

	function resetResults() {
		$results.prop('hidden', false);
		$accountRow.prop('hidden', true);
		$balanceRow.prop('hidden', true);
		$errorRow.prop('hidden', true);
		$accountName.text('');
		$balance.text('');
		$error.text('');
	}

	function showError(message) {
		resetResults();
		$status.text(wctfApiSettings.messages.failed);
		$error.text(message);
		$errorRow.prop('hidden', false);
	}

	$button.on('click', function () {
		resetResults();
		$status.text('');
		$button.prop('disabled', true);
		$spinner.addClass('is-active');

		$.ajax({
			url: wctfApiSettings.ajaxUrl,
			type: 'POST',
			dataType: 'json',
			data: {
				action: 'wctf_test_fazercards_connection',
				nonce: wctfApiSettings.nonce
			}
		})
			.done(function (response) {
				var data = response.data || {};

				if (!response.success) {
					showError(data.message || wctfApiSettings.messages.requestFailed);
					return;
				}

				$status.text(wctfApiSettings.messages.connected);

				if (data.accountName) {
					$accountName.text(data.accountName);
					$accountRow.prop('hidden', false);
				}

				if (undefined !== data.balance && null !== data.balance && '' !== data.balance) {
					$balance.text(
						data.currency ? data.balance + ' ' + data.currency : data.balance
					);
					$balanceRow.prop('hidden', false);
				}
			})
			.fail(function (xhr) {
				var message = wctfApiSettings.messages.requestFailed;

				if (
					xhr.responseJSON &&
					xhr.responseJSON.data &&
					xhr.responseJSON.data.message
				) {
					message = xhr.responseJSON.data.message;
				}

				showError(message);
			})
			.always(function () {
				$button.prop('disabled', false);
				$spinner.removeClass('is-active');
			});
	});
})(jQuery);
