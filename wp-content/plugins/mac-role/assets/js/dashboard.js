/**
 * User Dashboard JavaScript
 */

(function($) {
	'use strict';
	
	$(document).ready(function() {
		
		// Handle tile clicks
		$('.rud-tile').on('click', function(e) {
			e.preventDefault();
			var $tile = $(this);
			var url = $tile.data('url');
			var behavior = $tile.data('behavior');
			
			if (!url) {
				return;
			}
			
			handleLinkOpen(url, behavior);
		});
		
		// Handle keyboard navigation
		$('.rud-tile').on('keypress', function(e) {
			if (e.which === 13 || e.which === 32) { // Enter or Space
				e.preventDefault();
				$(this).trigger('click');
			}
		});
		
		// Handle open button clicks
		$('.rud-open-button').on('click', function(e) {
			e.preventDefault();
			e.stopPropagation();
			var $tile = $(this).closest('.rud-tile');
			var url = $tile.data('url');
			var behavior = $tile.data('behavior');
			
			if (!url) {
				return;
			}
			
			handleLinkOpen(url, behavior);
		});
		
		// Handle submenu link clicks
		$(document).on('click', '.rud-submenu-link', function(e) {
			e.preventDefault();
			e.stopPropagation();
			var url = $(this).data('url');
			var behavior = $(this).data('behavior');
			
			if (!url) {
				return;
			}
			
			handleLinkOpen(url, behavior);
		});
		
		// Close iframe modal
		$('#rud-close-iframe').on('click', function() {
			$('#rud-iframe-modal').removeClass('active').hide();
			$('#rud-iframe-content').attr('src', '');
		});
		
		// Close modal on escape key
		$(document).on('keydown', function(e) {
			if (e.key === 'Escape' && $('#rud-iframe-modal').is(':visible')) {
				$('#rud-close-iframe').trigger('click');
			}
		});
		
		// Close modal on background click
		$('#rud-iframe-modal').on('click', function(e) {
			if ($(e.target).is('#rud-iframe-modal')) {
				$('#rud-close-iframe').trigger('click');
			}
		});
		
		/**
		 * Handle link opening based on behavior
		 */
		function handleLinkOpen(url, behavior) {
			if (behavior === 'iframe' && rudDashboard.allowIframe) {
				// Open in iframe modal
				$('#rud-iframe-content').attr('src', url);
				$('#rud-iframe-modal').addClass('active').show();
			} else if (behavior === 'new') {
				// Open in new tab
				window.open(url, '_blank');
			} else {
				// Open in same tab
				window.location.href = url;
			}
		}
		
	});
	
})(jQuery);

