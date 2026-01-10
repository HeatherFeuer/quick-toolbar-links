/**
 * Quick Toolbar Scripts
 * Updated for WordPress 5.5+ jQuery compatibility
 */

function quictoliDelete(id, title) {
	if(confirm("Are you sure you want to delete '" + title + "'? If the item has children, they will also be deleted.")) {
		var data = {
			'action': 'quictoli_delete_custom_link',
			'quictoli_id': id,
			'nonce': quictoli_ajax.nonce
		};

		jQuery.post(quictoli_ajax.ajax_url, data, function(response) {
			if (response.success) {
				location.reload();
			} else {
				alert('Error deleting link. Please try again.');
			}
		});
	}
	return false;
}

// Use jQuery in no-conflict mode for better compatibility
(function($) {
	'use strict';
	
	$(document).ready(function() {
		var custom_uploader;

		// Media uploader for icon selection
		$('#_quictoli_upload_image_button').on('click', function(e) {
			e.preventDefault();

			// If the uploader object has already been created, reopen the dialog
			if (custom_uploader) {
				custom_uploader.open();
				return;
			}

			// Extend the wp.media object
			custom_uploader = wp.media.frames.file_frame = wp.media({
				title: 'Choose Image',
				button: {
					text: 'Choose Image'
				},
				multiple: false
			});

			// When a file is selected, grab the URL and set it as the text field's value
			custom_uploader.on('select', function() {
				var attachment = custom_uploader.state().get('selection').first().toJSON();
				$('#_quictoli_upload_image').val(attachment.url);
				if ($('#_quictoli_upload_image_label').length) {
					$('#_quictoli_upload_image_label').val(attachment.url);
				}
			});

			// Open the uploader dialog
			custom_uploader.open();
		});

		// Add "Select All/None" functionality for better UX
		if ($('#_quictoli_quicklinks_options').length) {
			var selectAllBtn = '<button type="button" class="button quictoli-select-all" style="margin: 10px 0;">' +
				'Select All</button> <button type="button" class="button quictoli-select-none" style="margin: 10px 0;">' +
				'Select None</button>';
			$('#_quictoli_quicklinks_options').before(selectAllBtn);
			
			$('.quictoli-select-all').on('click', function() {
				$('#_quictoli_quicklinks_options input[type="checkbox"]').prop('checked', true);
			});
			
			$('.quictoli-select-none').on('click', function() {
				$('#_quictoli_quicklinks_options input[type="checkbox"]').prop('checked', false);
			});
		}

		// Add confirmation for form submission
		$('form').on('submit', function() {
			var $form = $(this);
			if ($form.find('input[name="_quictoli_custom_title"]').length) {
				var title = $form.find('input[name="_quictoli_custom_title"]').val();
				var url = $form.find('input[name="_quictoli_custom_url"]').val();
				
				if (!title || !url) {
					alert('Please fill in both Title and URL fields.');
					return false;
				}
			}
		});

		// Enhance accessibility - add keyboard navigation
		$('.quictoli-menu-item').attr('role', 'menuitem');
		$('.quictoli-has-submenu').attr('aria-expanded', 'false');
		
		// Handle keyboard navigation for toolbar items
		$('.quictoli-has-submenu').on('keydown', function(e) {
			if (e.key === 'Enter' || e.key === ' ') {
				e.preventDefault();
				var expanded = $(this).attr('aria-expanded') === 'true';
				$(this).attr('aria-expanded', !expanded);
			}
		});
		
		// Show success message if URL parameter indicates update
		if (window.location.search.indexOf('updated=true') > -1) {
			var message = '<div class="notice notice-success is-dismissible"><p>Custom link added successfully!</p></div>';
			$('.wrap h2').first().after(message);
		}
	});
	
})(jQuery);
