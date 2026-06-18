/**
 * UD Audit Manager - Print Report Script
 *
 * Handles automatic print trigger and references localized variables.
 */
(function() {
	window.onload = function() {
		if (typeof udamPrint !== 'undefined') {
			console.log('UD Audit Manager: Launching print for run ID ' + udamPrint.runId);
		}
		window.print();
	};
})();
