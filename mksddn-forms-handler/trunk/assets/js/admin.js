/**
 * MksDdn Forms Handler - Admin JavaScript
 * 
 * @package MksDdnFormsHandler
 * @version 1.0.0
 */

(function($) {
    'use strict';

    // Main admin object
    var MksDdnFormsHandler = {
        
        /**
         * Initialize admin functionality
         */
        init: function() {
            this.bindEvents();
            this.initFormBuilder();
            this.initValidation();
        },

        /**
         * Bind event handlers
         */
        bindEvents: function() {
            // Form field management
            $(document).on('click', '.mksddn-add-field', this.addField);
            $(document).on('click', '.mksddn-remove-field', this.removeField);
            
            // Form preview
            $(document).on('click', '.mksddn-preview-form', this.previewForm);
            
            // Export functionality
            $(document).on('click', '.mksddn-export-csv', this.exportCSV);
			// Export by Date modal
			$(document).on('click', '.export-with-filters', this.openExportModal);
			$(document).on('click', '#export-modal', this.overlayCloseExportModal);
            
            // Settings tabs
            $(document).on('click', '.mksddn-tab-nav a', this.switchTab);
        },

        /**
         * Initialize form builder
         */
        initFormBuilder: function() {
            // Drag and drop functionality for form fields
            if ($('.mksddn-form-builder').length) {
                this.initDragAndDrop();
            }
        },

        /**
         * Initialize form validation
         */
        initValidation: function() {
            $('.mksddn-form-admin form').on('submit', function(e) {
                if (!MksDdnFormsHandler.validateForm($(this))) {
                    e.preventDefault();
                    return false;
                }
            });
        },

        /**
         * Add new field to form
         */
        addField: function(e) {
            e.preventDefault();
            
            var fieldType = $('#mksddn-field-type').val();
            var fieldTemplate = MksDdnFormsHandler.getFieldTemplate(fieldType);
            
            $('#mksddn-fields-container').append(fieldTemplate);
            MksDdnFormsHandler.updateFieldCount();
        },

        /**
         * Remove field from form
         */
        removeField: function(e) {
            e.preventDefault();
            
            if (confirm('Are you sure you want to remove this field?')) {
                $(this).closest('.mksddn-field-item').remove();
                MksDdnFormsHandler.updateFieldCount();
            }
        },

        /**
         * Get field template by type
         */
        getFieldTemplate: function(type) {
            var templates = {
                'text': '<div class="mksddn-field-item" data-type="text">' +
                        '<label>Text Field</label>' +
                        '<input type="text" name="field_text[]" placeholder="Enter field label">' +
                        '<button type="button" class="mksddn-remove-field">Remove</button>' +
                        '</div>',
                'email': '<div class="mksddn-field-item" data-type="email">' +
                         '<label>Email Field</label>' +
                         '<input type="email" name="field_email[]" placeholder="Enter field label">' +
                         '<button type="button" class="mksddn-remove-field">Remove</button>' +
                         '</div>',
                'textarea': '<div class="mksddn-field-item" data-type="textarea">' +
                           '<label>Textarea Field</label>' +
                           '<textarea name="field_textarea[]" placeholder="Enter field label"></textarea>' +
                           '<button type="button" class="mksddn-remove-field">Remove</button>' +
                           '</div>'
            };
            
            return templates[type] || templates['text'];
        },

        /**
         * Update field count
         */
        updateFieldCount: function() {
            var count = $('.mksddn-field-item').length;
            $('#mksddn-field-count').text(count);
        },

        /**
         * Preview form
         */
        previewForm: function(e) {
            e.preventDefault();
            
            var formData = $('.mksddn-form-admin form').serialize();
            
            $.ajax({
                url: ajaxurl,
                type: 'POST',
                data: {
                    action: 'mksddn_preview_form',
                    form_data: formData,
                    nonce: mksddn_ajax.nonce
                },
                success: function(response) {
                    if (response.success) {
                        $('#mksddn-preview-container').html(response.data.html);
                        $('#mksddn-preview-modal').show();
                    } else {
                        alert('Error generating preview: ' + response.data.message);
                    }
                },
                error: function() {
                    alert('Error generating preview. Please try again.');
                }
            });
        },

        /**
         * Export CSV
         */
        exportCSV: function(e) {
            e.preventDefault();
            
            var formId = $(this).data('form-id');
            var dateFrom = $('#mksddn-date-from').val();
            var dateTo = $('#mksddn-date-to').val();
            
            var url = ajaxurl + '?action=mksddn_export_csv' +
                     '&form_id=' + formId +
                     '&date_from=' + dateFrom +
                     '&date_to=' + dateTo +
                     '&nonce=' + mksddn_ajax.nonce;
            
            window.location.href = url;
        },

		/**
		 * Open Export by Date modal and populate form id/name
		 */
		openExportModal: function(e) {
			e.preventDefault();
			var $btn = $(this);
			var formId = $btn.data('form-id');
			var formName = $btn.data('form-name') || '';
			$('#modal-form-filter').val(formId);
			if (formName) {
				var exportByDateText = (typeof mksddn_fh_admin !== 'undefined' && mksddn_fh_admin.export_by_date_text) 
					? mksddn_fh_admin.export_by_date_text 
					: 'Export by Date';
				$('#modal-title').text(exportByDateText + ' — ' + formName);
			}
			$('#modal_date_from').val('');
			$('#modal_date_to').val('');
			$('#export-modal').show();
		},

		/**
		 * Close Export by Date modal
		 */
		hideExportModal: function() {
			$('#export-modal').hide();
		},

		/**
		 * Close modal when clicking on overlay background
		 */
		overlayCloseExportModal: function(e) {
			if (e.target && e.target.id === 'export-modal') {
				$('#export-modal').hide();
			}
		},

        /**
         * Switch tabs
         */
        switchTab: function(e) {
            e.preventDefault();
            
            var target = $(this).attr('href');
            
            // Hide all tabs
            $('.mksddn-tab-content').hide();
            
            // Show target tab
            $(target).show();
            
            // Update active tab
            $('.mksddn-tab-nav a').removeClass('active');
            $(this).addClass('active');
        },

        /**
         * Validate form
         */
        validateForm: function($form) {
            var isValid = true;
            
            // Clear previous errors
            $form.find('.mksddn-error').remove();
            
            // Validate required fields
            $form.find('[required]').each(function() {
                var $field = $(this);
                var value = $field.val().trim();
                
                if (!value) {
                    isValid = false;
                    $field.addClass('mksddn-error');
                    $field.after('<span class="mksddn-error-message">This field is required.</span>');
                }
            });
            
            // Validate email fields
            $form.find('input[type="email"]').each(function() {
                var $field = $(this);
                var value = $field.val().trim();
                
                if (value && !MksDdnFormsHandler.isValidEmail(value)) {
                    isValid = false;
                    $field.addClass('mksddn-error');
                    $field.after('<span class="mksddn-error-message">Please enter a valid email address.</span>');
                }
            });
            
            return isValid;
        },

        /**
         * Validate email format
         */
        isValidEmail: function(email) {
            var emailRegex = /^[^\s@]+@[^\s@]+\.[^\s@]+$/;
            return emailRegex.test(email);
        },

        /**
         * Initialize drag and drop
         */
        initDragAndDrop: function() {
            $('.mksddn-form-builder').sortable({
                handle: '.mksddn-drag-handle',
                placeholder: 'mksddn-field-placeholder',
                update: function() {
                    MksDdnFormsHandler.updateFieldOrder();
                }
            });
        },

        /**
         * Update field order
         */
        updateFieldOrder: function() {
            var order = [];
            $('.mksddn-field-item').each(function(index) {
                order.push({
                    id: $(this).data('field-id'),
                    order: index
                });
            });
            
            // Save order via AJAX
            $.ajax({
                url: ajaxurl,
                type: 'POST',
                data: {
                    action: 'mksddn_update_field_order',
                    order: order,
                    nonce: mksddn_ajax.nonce
                }
            });
        },

        /**
         * Show notification
         */
        showNotification: function(message, type) {
            type = type || 'success';
            
            var $notification = $('<div class="mksddn-notification mksddn-notification-' + type + '">' + message + '</div>');
            
            $('body').append($notification);
            
            setTimeout(function() {
                $notification.fadeOut(function() {
                    $(this).remove();
                });
            }, 3000);
        }
    };

    // Initialize when document is ready
    $(document).ready(function() {
        MksDdnFormsHandler.init();
    });

    // Provide global close function used in PHP markup (onclick)
    window.closeExportModal = function() {
        MksDdnFormsHandler.hideExportModal();
    };

})(jQuery); 