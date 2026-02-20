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
            this.initTabs();
        },

        /**
         * Bind event handlers
         */
        bindEvents: function() {
            var self = this;
            
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
            
            // Settings tabs - use direct binding for better compatibility
            $(document).on('click', '.mksddn-tab-nav', function(e) {
                self.switchTab.call(this, e);
            });
            
            // Telegram custom template toggle
            $(document).on('change', '#use_custom_telegram_template', this.toggleTelegramTemplate);
            
            // Initialize telegram template visibility on page load
            this.initTelegramTemplate();
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
            
            var i18n = (typeof mksddn_fh_admin !== 'undefined') ? mksddn_fh_admin : {};
            var confirmText = i18n.confirm_remove_field || 'Are you sure you want to remove this field?';
            
            if (confirm(confirmText)) {
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
                    var i18n = (typeof mksddn_fh_admin !== 'undefined') ? mksddn_fh_admin : {};
                    
                    if (response.success) {
                        $('#mksddn-preview-container').html(response.data.html);
                        $('#mksddn-preview-modal').show();
                    } else {
                        var errorText = (i18n.error_generating_preview || 'Error generating preview:') + ' ' + (response.data.message || '');
                        alert(errorText);
                    }
                },
                error: function() {
                    var i18n = (typeof mksddn_fh_admin !== 'undefined') ? mksddn_fh_admin : {};
                    alert(i18n.error_generating_preview_retry || 'Error generating preview. Please try again.');
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
         * Initialize tabs
         */
        initTabs: function() {
            var $tabs = $('.mksddn-form-tabs');
            if (!$tabs.length) {
                return;
            }
            
            this.hideAllTabContents($tabs);
            var $activeContent = this.findActiveTab($tabs) || this.activateFirstTab($tabs);
            if ($activeContent) {
                $activeContent.addClass('active').show();
            }
        },

        /**
         * Hide all tab contents
         */
        hideAllTabContents: function($tabs) {
            $tabs.find('.mksddn-form-tab-content').removeClass('active').hide();
        },

        /**
         * Find existing active tab and return its content
         */
        findActiveTab: function($tabs) {
            var $activeTab = $tabs.find('.mksddn-tab-nav.active');
            if ($activeTab.length > 0) {
                var activeHref = $activeTab.attr('href');
                if (activeHref) {
                    var $activeContent = $(activeHref);
                    if ($activeContent.length) {
                        return $activeContent;
                    }
                }
            }
            return null;
        },

        /**
         * Activate first tab and return its content
         */
        activateFirstTab: function($tabs) {
            var $firstTab = $tabs.find('.mksddn-tab-nav').first();
            $tabs.find('.mksddn-tab-nav').removeClass('active');
            $firstTab.addClass('active');
            
            var firstTabHref = $firstTab.attr('href');
            if (firstTabHref) {
                return $(firstTabHref);
            }
            return null;
        },

        /**
         * Switch tabs
         */
        switchTab: function(e) {
            if (e) {
                e.preventDefault();
                e.stopPropagation();
            }
            
            var $link = $(this);
            var target = $link.attr('href');
            
            if (!target) {
                return false;
            }
            
            var $tabs = $link.closest('.mksddn-form-tabs');
            if (!$tabs.length) {
                $tabs = $('.mksddn-form-tabs').first();
            }
            
            if (!$tabs.length) {
                return false;
            }
            
            // Hide all tab contents
            $tabs.find('.mksddn-form-tab-content').removeClass('active').hide();
            
            // Show target tab content
            var $targetContent = $(target);
            if ($targetContent.length) {
                $targetContent.addClass('active').show();
            }
            
            // Update active tab nav
            $tabs.find('.mksddn-tab-nav').removeClass('active');
            $link.addClass('active');
            
            return false;
        },

        /**
         * Validate form
         */
        validateForm: function($form) {
            var isValid = true;
            var i18n = (typeof mksddn_fh_admin !== 'undefined') ? mksddn_fh_admin : {};
            
            // Clear previous errors
            $form.find('.mksddn-error').remove();
            
            // Validate required fields
            $form.find('[required]').each(function() {
                var $field = $(this);
                var value = $field.val().trim();
                
                if (!value) {
                    isValid = false;
                    $field.addClass('mksddn-error');
                    var errorMsg = i18n.field_required || 'This field is required.';
                    $field.after('<span class="mksddn-error-message">' + errorMsg + '</span>');
                }
            });
            
            // Validate email fields
            $form.find('input[type="email"]').each(function() {
                var $field = $(this);
                var value = $field.val().trim();
                
                if (value && !MksDdnFormsHandler.isValidEmail(value)) {
                    isValid = false;
                    $field.addClass('mksddn-error');
                    var errorMsg = i18n.enter_valid_email || 'Please enter a valid email address.';
                    $field.after('<span class="mksddn-error-message">' + errorMsg + '</span>');
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
        },

        /**
         * Toggle Telegram template field visibility
         */
        toggleTelegramTemplate: function() {
            var $checkbox = $(this);
            var $templateRow = $('.mksddn-telegram-template-row');
            
            if ($checkbox.is(':checked')) {
                $templateRow.slideDown();
                
                    // If template is empty, populate with default template from data attribute
                    var $templateField = $('#telegram_template');
                    if (!$templateField.val() || $templateField.val().trim() === '') {
                        var defaultTemplate = $templateRow.data('default-template');
                        if (defaultTemplate) {
                            $templateField.val(defaultTemplate);
                        }
                    }
            } else {
                $templateRow.slideUp();
            }
        },

        /**
         * Initialize Telegram template visibility on page load
         */
        initTelegramTemplate: function() {
            var $checkbox = $('#use_custom_telegram_template');
            if ($checkbox.length) {
                // Set initial visibility state
                var $templateRow = $('.mksddn-telegram-template-row');
                if ($checkbox.is(':checked')) {
                    $templateRow.show();
                    
                    // If template is empty, populate with default template from data attribute
                    var $templateField = $('#telegram_template');
                    if (!$templateField.val() || $templateField.val().trim() === '') {
                        var defaultTemplate = $templateRow.data('default-template');
                        if (defaultTemplate) {
                            $templateField.val(defaultTemplate);
                        }
                    }
                } else {
                    $templateRow.hide();
                }
            }
        }
    };

    // Initialize when document is ready
    $(document).ready(function() {
        MksDdnFormsHandler.init();
    });

    // Also initialize on window load for meta boxes that load dynamically
    $(window).on('load', function() {
        if ($('.mksddn-form-tabs').length && !$('.mksddn-form-tabs').data('initialized')) {
            MksDdnFormsHandler.initTabs();
            $('.mksddn-form-tabs').data('initialized', true);
        }
    });

    // Handle dynamically loaded meta boxes (WordPress sometimes loads them via AJAX)
    if (typeof wp !== 'undefined' && wp.domReady) {
        wp.domReady(function() {
            setTimeout(function() {
                if ($('.mksddn-form-tabs').length) {
                    MksDdnFormsHandler.initTabs();
                }
            }, 100);
        });
    }

    // Provide global close function used in PHP markup (onclick)
    window.closeExportModal = function() {
        MksDdnFormsHandler.hideExportModal();
    };

})(jQuery); 