(function($) {
	'use strict';

	if (typeof apo_vars === 'undefined') {
		return;
	}

	var ajax_url  = apo_vars.ajax_url;
	var nonce     = apo_vars.nonce;
	var mode      = apo_vars.mode;
	var term_id   = apo_vars.term_id;
	var post_type = apo_vars.post_type;
	var i18n      = apo_vars.i18n || {};

	var previousOrder = null;
	var debounceTimer = null;
	var keyboardActiveRow = null;
	var keyboardOriginalIndex = null;

	// Fix column widths during drag to prevent table collapse
	function fixHelper(e, ui) {
		ui.children().each(function() {
			$(this).width($(this).width());
		});
		return ui;
	}

	// Show a brief notification with optional undo
	function showNotice(type, message, undoCallback) {
		var $p = $('<p></p>').text(message);
		if (undoCallback) {
			var $undo = $('<a href="#" class="apo-undo-link"></a>').text(i18n.undo || 'Undo');
			$p.append(' ').append($undo);
		}
		var $notice = $('<div class="notice notice-' + type + ' apo-ajax-notice"></div>').append($p);
		if (undoCallback) {
			$notice.find('.apo-undo-link').on('click', function(e) {
				e.preventDefault();
				$notice.remove();
				undoCallback();
			});
		}
		$('.apo-ajax-notice').remove();
		$('.wrap > h1, .wrap > .wp-header-end').first().after($notice);
		setTimeout(function() {
			$notice.fadeOut(300, function() { $(this).remove(); });
		}, undoCallback ? 8000 : 2000);
	}

	// Send order via AJAX (used by both drag-and-drop and undo)
	function saveOrder(serializedOrder, callback) {
		var action = mode === 'term' ? 'apo_save_term_post_order' : 'apo_save_global_order';

		var data = {
			action: action,
			order: serializedOrder,
			nonce: nonce
		};

		if (mode === 'term') {
			data.term_id = term_id;
		}

		$.post(ajax_url, data, function(response) {
			if (callback) callback(response.success);
		}).fail(function() {
			if (callback) callback(false);
		});
	}

	// Highlight saved rows
	function highlightRows($list) {
		$list.find('tr').addClass('apo-highlight');
		setTimeout(function() {
			$list.find('tr').removeClass('apo-highlight');
		}, 1000);
	}

	// Update order column numbers
	function updateOrderNumbers($list) {
		$list.find('tr').each(function(index) {
			$(this).find('.column-apo_order').text(index);
		});
	}

	// ARIA live region for screen reader announcements
	function announce(message) {
		var $region = $('#apo-live-region');
		if (!$region.length) {
			$region = $('<div id="apo-live-region" class="screen-reader-text" aria-live="assertive" role="status"></div>');
			$('body').append($region);
		}
		$region.text(message);
	}

	$(document).ready(function() {
		var $table = $('table.wp-list-table');
		var $list = $table.find('#the-list');

		if (!$list.length) {
			return;
		}

		// Detect touch device
		var isTouch = ('ontouchstart' in window) || (navigator.maxTouchPoints > 0);

		// --- Reset Order button (#6) ---
		var $resetWrap = $('<div class="apo-reset-wrap alignleft actions"></div>');
		var $sortSelect = $('<select class="apo-reset-sort">' +
			'<option value="date_desc">' + (i18n.date_desc || 'Date (newest first)') + '</option>' +
			'<option value="date_asc">' + (i18n.date_asc || 'Date (oldest first)') + '</option>' +
			'<option value="title_asc">' + (i18n.title_asc || 'Title (A-Z)') + '</option>' +
			'<option value="title_desc">' + (i18n.title_desc || 'Title (Z-A)') + '</option>' +
			'</select>');
		var $resetBtn = $('<button type="button" class="button apo-reset-btn">' +
			(i18n.reset_order || 'Reset Order') + '</button>');

		$resetWrap.append($sortSelect).append($resetBtn);
		$('.tablenav.top .actions:last').after($resetWrap);

		$resetBtn.on('click', function() {
			if (!confirm(i18n.reset_confirm || 'Are you sure you want to reset the order? This cannot be undone.')) {
				return;
			}

			var resetData = {
				action: 'apo_reset_order',
				nonce: nonce,
				reset_type: mode === 'term' ? 'term' : 'global',
				sort_by: $sortSelect.val(),
				post_type: post_type
			};

			if (mode === 'term') {
				resetData.term_id = term_id;
			}

			$.post(ajax_url, resetData, function(response) {
				if (response.success) {
					showNotice('success', i18n.reset_success || 'Order has been reset.');
					setTimeout(function() { location.reload(); }, 800);
				} else {
					showNotice('error', i18n.reset_failed || 'Failed to reset order.');
				}
			}).fail(function() {
				showNotice('error', i18n.reset_failed || 'Failed to reset order.');
			});
		});

		// --- Sortable init with undo (#7), visual feedback (#8), debounce (#9), touch (#10) ---
		var sortableOptions = {
			items: 'tr',
			axis: 'y',
			helper: fixHelper,
			placeholder: 'apo-sortable-placeholder',
			cursor: 'grabbing',
			opacity: 0.8,
			tolerance: 'pointer',
			start: function(e, ui) {
				// Capture current order for undo
				previousOrder = $list.sortable('serialize');

				// Lock table layout to prevent column reflow during drag
				$table.css({
					'width': $table.outerWidth() + 'px',
					'table-layout': 'fixed'
				});
				$table.find('thead th, thead td').each(function() {
					$(this).css('width', $(this).outerWidth() + 'px');
				});
				ui.placeholder.height(ui.helper.outerHeight());
			},
			stop: function() {
				$table.css({
					'width': '',
					'table-layout': ''
				});
				$table.find('thead th, thead td').css('width', '');
			},
			update: function() {
				// Debounce: wait 800ms before sending AJAX (#9)
				if (debounceTimer) {
					clearTimeout(debounceTimer);
				}

				var savedPreviousOrder = previousOrder;

				debounceTimer = setTimeout(function() {
					var order = $list.sortable('serialize');

					saveOrder(order, function(success) {
						if (success) {
							highlightRows($list);
							updateOrderNumbers($list);
							showNotice('success', i18n.order_saved || 'Order saved.', function() {
								// Undo callback
								if (savedPreviousOrder) {
									saveOrder(savedPreviousOrder, function(undoSuccess) {
										if (undoSuccess) {
											showNotice('success', i18n.order_reverted || 'Order reverted.');
											location.reload();
										} else {
											showNotice('error', i18n.save_failed || 'Failed to save order.');
										}
									});
								}
							});
						} else {
							showNotice('error', i18n.save_failed || 'Failed to save order.');
						}
					});
				}, 800);
			}
		};

		// Touch support: increase distance to prevent accidental drags (#10)
		if (isTouch) {
			sortableOptions.distance = 10;
		}

		$list.sortable(sortableOptions);

		// Add drag cursor class and accessibility attributes (#11)
		$list.find('tr').each(function() {
			$(this).addClass('apo-draggable')
				.attr('tabindex', '0')
				.attr('role', 'listitem');
		});
		$list.attr('role', 'list');

		// --- Keyboard accessibility (#11) ---
		$list.on('keydown', 'tr', function(e) {
			var $row = $(this);

			// Enter or Space: toggle reorder mode
			if ((e.key === 'Enter' || e.key === ' ') && !keyboardActiveRow) {
				e.preventDefault();
				keyboardActiveRow = $row;
				keyboardOriginalIndex = $row.index();
				previousOrder = $list.sortable('serialize');
				$row.addClass('apo-keyboard-active');
				announce(i18n.keyboard_activated || 'Reorder mode activated. Use arrow keys to move, Enter to save, Escape to cancel.');
				return;
			}

			if (!keyboardActiveRow || !keyboardActiveRow.is($row)) {
				return;
			}

			// Arrow Up: move row up
			if (e.key === 'ArrowUp') {
				e.preventDefault();
				var $prev = $row.prev('tr');
				if ($prev.length) {
					$row.insertBefore($prev);
					$row.focus();
					announce(i18n.keyboard_moved_up || 'Moved up.');
				}
				return;
			}

			// Arrow Down: move row down
			if (e.key === 'ArrowDown') {
				e.preventDefault();
				var $next = $row.next('tr');
				if ($next.length) {
					$row.insertAfter($next);
					$row.focus();
					announce(i18n.keyboard_moved_down || 'Moved down.');
				}
				return;
			}

			// Enter: save position
			if (e.key === 'Enter') {
				e.preventDefault();
				$row.removeClass('apo-keyboard-active');
				var savedPrev = previousOrder;
				keyboardActiveRow = null;

				var order = $list.sortable('serialize');
				saveOrder(order, function(success) {
					if (success) {
						highlightRows($list);
						updateOrderNumbers($list);
						announce(i18n.keyboard_saved || 'Position saved.');
						showNotice('success', i18n.order_saved || 'Order saved.', function() {
							if (savedPrev) {
								saveOrder(savedPrev, function(undoSuccess) {
									if (undoSuccess) {
										showNotice('success', i18n.order_reverted || 'Order reverted.');
										location.reload();
									}
								});
							}
						});
					} else {
						showNotice('error', i18n.save_failed || 'Failed to save order.');
					}
				});
				return;
			}

			// Escape: cancel
			if (e.key === 'Escape') {
				e.preventDefault();
				$row.removeClass('apo-keyboard-active');
				keyboardActiveRow = null;

				// Restore original position
				var $rows = $list.find('tr');
				var currentIndex = $row.index();
				if (currentIndex !== keyboardOriginalIndex) {
					$row.detach();
					if (keyboardOriginalIndex === 0) {
						$list.prepend($row);
					} else {
						$list.find('tr').eq(keyboardOriginalIndex - 1).after($row);
					}
					$row.focus();
				}
				announce(i18n.keyboard_cancelled || 'Reorder cancelled.');
				return;
			}
		});
	});
})(jQuery);
