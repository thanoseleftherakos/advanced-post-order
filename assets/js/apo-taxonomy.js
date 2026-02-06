(function($) {
	'use strict';

	if (typeof apo_tax_vars === 'undefined') {
		return;
	}

	var ajax_url = apo_tax_vars.ajax_url;
	var nonce    = apo_tax_vars.nonce;

	// Fix column widths during drag
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
		var $table = $('table.tags, table.wp-list-table').first();
		var $list = $table.find('#the-list');

		if (!$list.length) {
			return;
		}

		$list.sortable({
			items: 'tr',
			axis: 'y',
			helper: fixHelper,
			placeholder: 'apo-sortable-placeholder',
			cursor: 'grabbing',
			opacity: 0.8,
			tolerance: 'pointer',
			start: function(e, ui) {
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

				$.post(ajax_url, {
					action: 'apo_save_term_order',
					order: order,
					nonce: nonce
				}, function(response) {
					if (response.success) {
						showNotice('success', 'Term order saved.');
					} else {
						showNotice('error', 'Failed to save term order.');
					}
				}).fail(function() {
					showNotice('error', 'Failed to save term order.');
				});
			}
		});

		// Add drag cursor class
		$list.find('tr').addClass('apo-draggable');
	});
})(jQuery);
