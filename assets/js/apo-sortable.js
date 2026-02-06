(function($) {
	'use strict';

	if (typeof apo_vars === 'undefined') {
		return;
	}

	var ajax_url = apo_vars.ajax_url;
	var nonce    = apo_vars.nonce;
	var mode     = apo_vars.mode;
	var term_id  = apo_vars.term_id;

	// Fix column widths during drag to prevent table collapse
	function fixHelper(e, ui) {
		ui.children().each(function() {
			$(this).width($(this).width());
		});
		return ui;
	}

	// Show a brief notification
	function showNotice(type, message) {
		var $notice = $('<div class="notice notice-' + type + ' apo-ajax-notice"><p>' + message + '</p></div>');
		$('.apo-ajax-notice').remove();
		$('.wrap > h1, .wrap > .wp-header-end').first().after($notice);
		setTimeout(function() {
			$notice.fadeOut(300, function() { $(this).remove(); });
		}, 2000);
	}

	$(document).ready(function() {
		var $table = $('table.wp-list-table');
		var $list = $table.find('#the-list');

		if (!$list.length) {
			return;
		}

		// Disable column sorting links to prevent confusion
		$('table.wp-list-table thead th a, table.wp-list-table tfoot th a').on('click', function() {
			// Allow column sort but it will reload the page, disabling drag-and-drop
		});

		$list.sortable({
			items: 'tr',
			axis: 'y',
			helper: fixHelper,
			placeholder: 'apo-sortable-placeholder',
			cursor: 'grabbing',
			opacity: 0.8,
			tolerance: 'pointer',
			start: function(e, ui) {
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
				var order = $list.sortable('serialize');
				var action = mode === 'term' ? 'apo_save_term_post_order' : 'apo_save_global_order';

				var data = {
					action: action,
					order: order,
					nonce: nonce
				};

				if (mode === 'term') {
					data.term_id = term_id;
				}

				$.post(ajax_url, data, function(response) {
					if (response.success) {
						showNotice('success', 'Order saved.');
					} else {
						showNotice('error', 'Failed to save order.');
					}
				}).fail(function() {
					showNotice('error', 'Failed to save order.');
				});
			}
		});

		// Add drag cursor class
		$list.find('tr').addClass('apo-draggable');
	});
})(jQuery);
