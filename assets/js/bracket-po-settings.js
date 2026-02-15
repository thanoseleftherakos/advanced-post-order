document.addEventListener('DOMContentLoaded', function() {
	var ptCheckboxes  = document.querySelectorAll('input[name="bracket_po_settings[post_types][]"]');
	var taxToggles    = document.querySelectorAll('.bracket-po-tax-toggle');
	var emptyMsg      = document.getElementById('bracket-po-tax-empty');

	function updateTaxonomies() {
		var activePts = [];
		ptCheckboxes.forEach(function(cb) {
			if (cb.checked) activePts.push(cb.value);
		});

		var visibleCount = 0;
		taxToggles.forEach(function(toggle) {
			var pts = toggle.getAttribute('data-post-types').split(',');
			var match = pts.some(function(pt) { return activePts.indexOf(pt) !== -1; });
			toggle.classList.toggle('bracket-po-visible', match);
			if (match) visibleCount++;
		});

		emptyMsg.style.display = visibleCount > 0 ? 'none' : '';
	}

	ptCheckboxes.forEach(function(cb) {
		cb.addEventListener('change', updateTaxonomies);
	});

	updateTaxonomies();
});
