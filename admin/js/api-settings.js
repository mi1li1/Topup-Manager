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

	var $categoryButton = $('#wctf-sync-categories');

	if (!$categoryButton.length) {
		return;
	}

	var $categorySpinner = $('#wctf-sync-categories-spinner');
	var $categoryResults = $('#wctf-category-sync-results');
	var $categoryStatus = $('#wctf-category-sync-status');
	var $categoryTotalRow = $('#wctf-category-total-row');
	var $categoryTotal = $('#wctf-category-total');
	var $categoryCreatedRow = $('#wctf-category-created-row');
	var $categoryCreated = $('#wctf-category-created');
	var $categoryUpdatedRow = $('#wctf-category-updated-row');
	var $categoryUpdated = $('#wctf-category-updated');
	var $categorySkippedRow = $('#wctf-category-skipped-row');
	var $categorySkipped = $('#wctf-category-skipped');
	var $categoryErrorRow = $('#wctf-category-error-row');
	var $categoryError = $('#wctf-category-sync-error');

	function resetCategoryResults() {
		$categoryResults.prop('hidden', false);
		$categoryTotalRow.prop('hidden', true);
		$categoryCreatedRow.prop('hidden', true);
		$categoryUpdatedRow.prop('hidden', true);
		$categorySkippedRow.prop('hidden', true);
		$categoryErrorRow.prop('hidden', true);
		$categoryTotal.text('');
		$categoryCreated.text('');
		$categoryUpdated.text('');
		$categorySkipped.text('');
		$categoryError.text('');
	}

	function showCategoryError(message) {
		resetCategoryResults();
		$categoryStatus.text(wctfApiSettings.messages.syncFailed);
		$categoryError.text(message);
		$categoryErrorRow.prop('hidden', false);
	}

	$categoryButton.on('click', function () {
		resetCategoryResults();
		$categoryStatus.text(wctfApiSettings.messages.syncing);
		$categoryButton.prop('disabled', true);
		$categorySpinner.addClass('is-active');

		$.ajax({
			url: wctfApiSettings.ajaxUrl,
			type: 'POST',
			dataType: 'json',
			data: {
				action: 'wctf_sync_fazercards_categories',
				nonce: wctfApiSettings.categoryNonce
			}
		})
			.done(function (response) {
				var data = response.data || {};

				if (!response.success) {
					showCategoryError(
						data.message || wctfApiSettings.messages.syncRequestFailed
					);
					return;
				}

				$categoryStatus.text(wctfApiSettings.messages.synced);
				$categoryTotal.text(data.total);
				$categoryCreated.text(data.created);
				$categoryUpdated.text(data.updated);
				$categorySkipped.text(data.skipped);
				$categoryTotalRow.prop('hidden', false);
				$categoryCreatedRow.prop('hidden', false);
				$categoryUpdatedRow.prop('hidden', false);
				$categorySkippedRow.prop('hidden', false);
			})
			.fail(function (xhr) {
				var message = wctfApiSettings.messages.syncRequestFailed;

				if (
					xhr.responseJSON &&
					xhr.responseJSON.data &&
					xhr.responseJSON.data.message
				) {
					message = xhr.responseJSON.data.message;
				}

				showCategoryError(message);
			})
			.always(function () {
				$categoryButton.prop('disabled', false);
				$categorySpinner.removeClass('is-active');
			});
	});
})(jQuery);
