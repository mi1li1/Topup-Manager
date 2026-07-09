/* global jQuery, wctfGiftCardSettings */

(function ($) {
	'use strict';

	if ('undefined' === typeof wctfGiftCardSettings) {
		return;
	}

	function responseErrorData(xhr) {
		if (xhr.responseJSON && xhr.responseJSON.data) {
			return xhr.responseJSON.data;
		}

		return {};
	}

	function safeValue(value) {
		if (undefined === value || null === value) {
			return '';
		}

		return String(value);
	}

	var $categoryButton = $('#wctf-giftcard-sync-categories');

	if ($categoryButton.length) {
		var $categorySpinner = $('#wctf-giftcard-sync-categories-spinner');
		var $categoryResults = $('#wctf-giftcard-category-sync-results');
		var $categoryStatus = $('#wctf-giftcard-category-sync-status');
		var $categoryTotal = $('#wctf-giftcard-category-total');
		var $categoryCreated = $('#wctf-giftcard-category-created');
		var $categoryUpdated = $('#wctf-giftcard-category-updated');
		var $categorySkipped = $('#wctf-giftcard-category-skipped');
		var $categoryErrorRow = $('#wctf-giftcard-category-error-row');
		var $categoryError = $('#wctf-giftcard-category-error');

		function resetCategoryResults() {
			$categoryResults.prop('hidden', false);
			$categoryTotal.text('0');
			$categoryCreated.text('0');
			$categoryUpdated.text('0');
			$categorySkipped.text('0');
			$categoryError.text('');
			$categoryErrorRow.prop('hidden', true);
		}

		function showCategoryError(message) {
			$categoryStatus.text(wctfGiftCardSettings.messages.categorySyncFailed);
			$categoryError.text(message);
			$categoryErrorRow.prop('hidden', false);
		}

		$categoryButton.on('click', function () {
			resetCategoryResults();
			$categoryStatus.text(wctfGiftCardSettings.messages.categorySyncing);
			$categoryButton.prop('disabled', true);
			$categorySpinner.addClass('is-active');

			$.ajax({
				url: wctfGiftCardSettings.ajaxUrl,
				type: 'POST',
				dataType: 'json',
				data: {
					action: 'wctf_sync_fazercards_giftcard_categories',
					nonce: wctfGiftCardSettings.categoryNonce
				}
			})
				.done(function (response) {
					var data = response.data || {};

					if (!response.success) {
						showCategoryError(
							data.message || wctfGiftCardSettings.messages.categoryRequestFailed
						);
						return;
					}

					$categoryStatus.text(wctfGiftCardSettings.messages.categoriesSynced);
					$categoryTotal.text(safeValue(data.total));
					$categoryCreated.text(safeValue(data.created));
					$categoryUpdated.text(safeValue(data.updated));
					$categorySkipped.text(safeValue(data.skipped));
				})
				.fail(function (xhr) {
					var data = responseErrorData(xhr);

					showCategoryError(
						data.message || wctfGiftCardSettings.messages.categoryRequestFailed
					);
				})
				.always(function () {
					$categoryButton.prop('disabled', false);
					$categorySpinner.removeClass('is-active');
				});
		});
	}

	var $cardButton = $('#wctf-giftcard-sync-cards');

	if ($cardButton.length) {
		var $cardSpinner = $('#wctf-giftcard-sync-cards-spinner');
		var $cardResults = $('#wctf-giftcard-card-sync-results');
		var $cardStatus = $('#wctf-giftcard-card-sync-status');
		var $cardProcessed = $('#wctf-giftcard-card-processed-categories');
		var $cardCategories = $('#wctf-giftcard-card-total-categories');
		var $cardTotal = $('#wctf-giftcard-card-total');
		var $cardCreated = $('#wctf-giftcard-card-created');
		var $cardUpdated = $('#wctf-giftcard-card-updated');
		var $cardSkipped = $('#wctf-giftcard-card-skipped');
		var $cardFailed = $('#wctf-giftcard-card-failed-categories');
		var $cardErrorRow = $('#wctf-giftcard-card-error-row');
		var $cardError = $('#wctf-giftcard-card-error');

		function resetCardResults() {
			$cardResults.prop('hidden', false);
			$cardProcessed.text('0');
			$cardCategories.text('0');
			$cardTotal.text('0');
			$cardCreated.text('0');
			$cardUpdated.text('0');
			$cardSkipped.text('0');
			$cardFailed.text('0');
			$cardError.text('');
			$cardErrorRow.prop('hidden', true);
		}

		function updateCardProgress(data) {
			if (undefined !== data.processedCategories) {
				$cardProcessed.text(safeValue(data.processedCategories));
			}

			if (undefined !== data.totalCategories) {
				$cardCategories.text(safeValue(data.totalCategories));
			}

			if (undefined !== data.totalCards) {
				$cardTotal.text(safeValue(data.totalCards));
			}

			if (undefined !== data.created) {
				$cardCreated.text(safeValue(data.created));
			}

			if (undefined !== data.updated) {
				$cardUpdated.text(safeValue(data.updated));
			}

			if (undefined !== data.skipped) {
				$cardSkipped.text(safeValue(data.skipped));
			}

			if (undefined !== data.failedCategories) {
				$cardFailed.text(safeValue(data.failedCategories));
			}
		}

		function finishCardSync() {
			$cardButton.prop('disabled', false);
			$cardSpinner.removeClass('is-active');
		}

		function showCardError(message, data) {
			if (data) {
				updateCardProgress(data);
			}

			$cardStatus.text(wctfGiftCardSettings.messages.cardSyncFailed);
			$cardError.text(message);
			$cardErrorRow.prop('hidden', false);
			finishCardSync();
		}

		function processCardBatch(syncToken) {
			$.ajax({
				url: wctfGiftCardSettings.ajaxUrl,
				type: 'POST',
				dataType: 'json',
				data: {
					action: 'wctf_sync_fazercards_giftcard_cards',
					nonce: wctfGiftCardSettings.cardNonce,
					operation: 'continue',
					syncToken: syncToken
				}
			})
				.done(function (response) {
					var data = response.data || {};

					if (!response.success) {
						showCardError(
							data.message || wctfGiftCardSettings.messages.cardRequestFailed,
							data
						);
						return;
					}

					updateCardProgress(data);

					if (data.complete) {
						$cardStatus.text(wctfGiftCardSettings.messages.cardsSynced);
						finishCardSync();
						return;
					}

					processCardBatch(data.syncToken || syncToken);
				})
				.fail(function (xhr) {
					var data = responseErrorData(xhr);

					showCardError(
						data.message || wctfGiftCardSettings.messages.cardRequestFailed,
						data
					);
				});
		}

		$cardButton.on('click', function () {
			resetCardResults();
			$cardStatus.text(wctfGiftCardSettings.messages.cardSyncing);
			$cardButton.prop('disabled', true);
			$cardSpinner.addClass('is-active');

			$.ajax({
				url: wctfGiftCardSettings.ajaxUrl,
				type: 'POST',
				dataType: 'json',
				data: {
					action: 'wctf_sync_fazercards_giftcard_cards',
					nonce: wctfGiftCardSettings.cardNonce,
					operation: 'start'
				}
			})
				.done(function (response) {
					var data = response.data || {};

					if (!response.success || !data.syncToken) {
						showCardError(
							data.message || wctfGiftCardSettings.messages.cardRequestFailed,
							data
						);
						return;
					}

					updateCardProgress(data);
					processCardBatch(data.syncToken);
				})
				.fail(function (xhr) {
					var data = responseErrorData(xhr);

					showCardError(
						data.message || wctfGiftCardSettings.messages.cardRequestFailed,
						data
					);
				});
		});
	}

	var $browser = $('#wctf-giftcard-browser');

	if (!$browser.length) {
		return;
	}

	var $browserSearch = $('#wctf-giftcard-browser-search');
	var $browserCategory = $('#wctf-giftcard-browser-category');
	var $browserSubmit = $('#wctf-giftcard-browser-submit');
	var $browserReset = $('#wctf-giftcard-browser-reset');
	var $browserError = $('#wctf-giftcard-browser-error');
	var $browserErrorMessage = $('#wctf-giftcard-browser-error-message');
	var $browserEmpty = $('#wctf-giftcard-browser-empty');
	var $browserTable = $('#wctf-giftcard-browser-results');
	var $browserRows = $('#wctf-giftcard-browser-rows');
	var $browserTotal = $('#wctf-giftcard-browser-total-results');
	var $browserPrevious = $('#wctf-giftcard-browser-previous');
	var $browserCurrent = $('#wctf-giftcard-browser-current-page');
	var $browserPages = $('#wctf-giftcard-browser-total-pages');
	var $browserNext = $('#wctf-giftcard-browser-next');
	var currentPage = 1;
	var totalPages = 1;
	var isLoading = false;

	function setBrowserLoading(loading) {
		isLoading = loading;
		$browserSearch.prop('disabled', loading);
		$browserCategory.prop('disabled', loading);
		$browserSubmit.prop('disabled', loading);
		$browserReset.prop('disabled', loading);
		$browserPrevious.prop('disabled', loading || currentPage <= 1);
		$browserNext.prop('disabled', loading || currentPage >= totalPages);
		$browserTable.attr('aria-busy', loading ? 'true' : 'false');
	}

	function resetBrowserNotices() {
		$browserError.prop('hidden', true);
		$browserErrorMessage.text('');
		$browserEmpty.prop('hidden', true);
	}

	function showBrowserError(message) {
		resetBrowserNotices();
		$browserErrorMessage.text(message);
		$browserError.prop('hidden', false);
	}

	function createCell(value) {
		var cell = document.createElement('td');

		cell.textContent = safeValue(value);
		return cell;
	}

	function renderRows(items) {
		var fragment = document.createDocumentFragment();

		$browserRows.empty();

		items.forEach(function (item) {
			var row = document.createElement('tr');

			row.appendChild(createCell(item.category_id));
			row.appendChild(createCell(item.category_name));
			row.appendChild(createCell(item.card_id));
			row.appendChild(createCell(item.name));
			row.appendChild(createCell(item.price_usd));
			row.appendChild(createCell(item.currency));
			row.appendChild(createCell(item.region));
			row.appendChild(createCell(item.stock));
			row.appendChild(createCell(item.min_order_quantity));
			row.appendChild(createCell(item.max_order_quantity));
			fragment.appendChild(row);
		});

		$browserRows.append(fragment);
	}

	function updatePagination(data) {
		currentPage = parseInt(data.page, 10) || 1;
		totalPages = parseInt(data.total_pages, 10) || 1;

		$browserCurrent.text(currentPage);
		$browserPages.text(totalPages);
		$browserTotal.text(parseInt(data.total, 10) || 0);
		$browserPrevious.prop('disabled', isLoading || currentPage <= 1);
		$browserNext.prop('disabled', isLoading || currentPage >= totalPages);
	}

	function loadBrowserPage(page) {
		if (isLoading) {
			return;
		}

		if (!wctfGiftCardSettings.ajaxUrl || !wctfGiftCardSettings.browserNonce) {
			showBrowserError(wctfGiftCardSettings.messages.browserFailed);
			return;
		}

		resetBrowserNotices();
		setBrowserLoading(true);

		$.ajax({
			url: wctfGiftCardSettings.ajaxUrl,
			type: 'POST',
			dataType: 'json',
			data: {
				action: 'wctf_browse_fazercards_giftcards',
				nonce: wctfGiftCardSettings.browserNonce,
				page: page,
				search: $browserSearch.val(),
				category_id: $browserCategory.val()
			}
		})
			.done(function (response) {
				var data = response.data || {};
				var items = Array.isArray(data.items) ? data.items : [];

				if (!response.success) {
					showBrowserError(
						data.message || wctfGiftCardSettings.messages.browserFailed
					);
					return;
				}

				renderRows(items);
				updatePagination(data);

				if (!items.length) {
					$browserEmpty.prop('hidden', false);
				}
			})
			.fail(function (xhr) {
				var data = responseErrorData(xhr);

				showBrowserError(
					data.message || wctfGiftCardSettings.messages.browserFailed
				);
			})
			.always(function () {
				setBrowserLoading(false);
			});
	}

	$browserSubmit.on('click', function () {
		loadBrowserPage(1);
	});

	$browserSearch.on('keydown', function (event) {
		if ('Enter' === event.key) {
			event.preventDefault();
			loadBrowserPage(1);
		}
	});

	$browserReset.on('click', function () {
		$browserSearch.val('');
		$browserCategory.val('');
		loadBrowserPage(1);
	});

	$browserPrevious.on('click', function () {
		if (currentPage > 1) {
			loadBrowserPage(currentPage - 1);
		}
	});

	$browserNext.on('click', function () {
		if (currentPage < totalPages) {
			loadBrowserPage(currentPage + 1);
		}
	});

	loadBrowserPage(1);
})(jQuery);
