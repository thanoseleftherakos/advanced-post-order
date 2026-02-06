(function($) {
	'use strict';

	if (typeof apo_tax_vars === 'undefined') {
		return;
	}

	var ajax_url = apo_tax_vars.ajax_url;
	var nonce    = apo_tax_vars.nonce;
	var i18n     = apo_tax_vars.i18n || {};

	var previousOrder = null;
	var debounceTimer = null;
	var keyboardActiveRow = null;
	var keyboardOriginalIndex = null;

	// Fix column widths during drag
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

	// Send order via AJAX
	function saveOrder(serializedOrder, callback) {
		$.post(ajax_url, {
			action: 'apo_save_term_order',
			order: serializedOrder,
			nonce: nonce
		}, function(response) {
			if (callback) callback(response.success);
		}).fail(function() {
			if (callback) callback(false);
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
		var $table = $('table.tags, table.wp-list-table').first();
		var $list = $table.find('#the-list');

		if (!$list.length) {
			return;
		}

		// Detect touch device
		var isTouch = ('ontouchstart' in window) || (navigator.maxTouchPoints > 0);

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
				// Debounce: wait 800ms before sending AJAX
				if (debounceTimer) {
					clearTimeout(debounceTimer);
				}

				var savedPreviousOrder = previousOrder;

				debounceTimer = setTimeout(function() {
					var order = $list.sortable('serialize');

					saveOrder(order, function(success) {
						if (success) {
							showNotice('success', i18n.order_saved || 'Term order saved.', function() {
								// Undo callback
								if (savedPreviousOrder) {
									saveOrder(savedPreviousOrder, function(undoSuccess) {
										if (undoSuccess) {
											showNotice('success', i18n.order_reverted || 'Order reverted.');
											location.reload();
										} else {
											showNotice('error', i18n.save_failed || 'Failed to save term order.');
										}
									});
								}
							});
						} else {
							showNotice('error', i18n.save_failed || 'Failed to save term order.');
						}
					});
				}, 800);
			}
		};

		// Touch support
		if (isTouch) {
			sortableOptions.distance = 10;
		}

		$list.sortable(sortableOptions);

		// Add drag cursor class and accessibility attributes
		$list.find('tr').each(function() {
			$(this).addClass('apo-draggable')
				.attr('tabindex', '0')
				.attr('role', 'listitem');
		});
		$list.attr('role', 'list');

		// --- Keyboard accessibility ---
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

			// Arrow Up
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

			// Arrow Down
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

			// Enter: save
			if (e.key === 'Enter') {
				e.preventDefault();
				$row.removeClass('apo-keyboard-active');
				var savedPrev = previousOrder;
				keyboardActiveRow = null;

				var order = $list.sortable('serialize');
				saveOrder(order, function(success) {
					if (success) {
						announce(i18n.keyboard_saved || 'Position saved.');
						showNotice('success', i18n.order_saved || 'Term order saved.', function() {
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
						showNotice('error', i18n.save_failed || 'Failed to save term order.');
					}
				});
				return;
			}

			// Escape: cancel
			if (e.key === 'Escape') {
				e.preventDefault();
				$row.removeClass('apo-keyboard-active');
				keyboardActiveRow = null;

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
