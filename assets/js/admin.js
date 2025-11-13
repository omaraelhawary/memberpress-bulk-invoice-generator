jQuery(document).ready(function($) {
    'use strict';

    var batchProcessor = {
        isProcessing: false,
        progressInterval: null,
        currentBatch: 0,
        totalBatches: 0
    };

    // Initialize date pickers with default WordPress styling
    $('.mpbig-datepicker').datepicker({
        dateFormat: 'yy-mm-dd',
        changeMonth: true,
        changeYear: true,
        yearRange: '-10:+1',
        showAnim: 'fadeIn'
    });

    // Show/hide period options with smooth animation
    $('#mpbig-type').on('change', function() {
        var type = $(this).val();
        var $periodOptions = $('#mpbig-period-options');
        if (type === 'period') {
            $periodOptions.removeClass('mpbig-hidden').addClass('mpbig-slide-down mpbig-visible mpbig-fade-in');
            // Force reflow to trigger animation
            $periodOptions[0].offsetHeight;
            $periodOptions.addClass('mpbig-visible');
        } else {
            $periodOptions.removeClass('mpbig-visible').addClass('mpbig-slide-up');
            setTimeout(function() {
                $periodOptions.addClass('mpbig-hidden').removeClass('mpbig-slide-down mpbig-slide-up');
            }, 300);
        }
    });

    // Email validation helper function
    function isValidEmail(email) {
        var emailRegex = /^[^\s@]+@[^\s@]+\.[^\s@]+$/;
        return emailRegex.test(email);
    }

    // Enhanced form validation with better UX
    function validateForm() {
        var errors = [];
        
        // Check generation type
        var type = $('#mpbig-type').val();
        if (type === 'period') {
            var startDate = $('#mpbig-start-date').val();
            var endDate = $('#mpbig-end-date').val();
            
            if (!startDate || !endDate) {
                errors.push('Please select both start and end dates for period generation.');
            } else if (new Date(startDate) >= new Date(endDate)) {
                errors.push('End date must be after start date.');
            }
        }

        // Check if at least one status is selected
        var selectedStatuses = $('input[name="status[]"]:checked').length;
        if (selectedStatuses === 0) {
            errors.push('Please select at least one transaction status.');
        }

        // Check customer email format if provided
        var customerEmail = $('#mpbig-customer-email').val().trim();
        if (customerEmail && !isValidEmail(customerEmail)) {
            errors.push('Please enter a valid email address for the customer filter.');
        }

        return errors;
    }

    // Show validation errors in a modern way
    function showValidationErrors(errors) {
        var errorHtml = '<div class="mpbig-notice mpbig-notice-error"><p><strong>Please fix the following errors:</strong></p><ul>';
        errors.forEach(function(error) {
            errorHtml += '<li>' + error + '</li>';
        });
        errorHtml += '</ul></div>';
        
        // Remove any existing error messages
        $('.mpbig-notice-error').remove();
        
        // Insert error message at the top of the form
        $('#mpbig-form').prepend(errorHtml);
        
        // Scroll to the error message
        $('html, body').animate({
            scrollTop: $('.mpbig-notice-error').offset().top - 100
        }, 500);
    }

    // Form submission with enhanced UX
    $('#mpbig-form').on('submit', function(e) {
        e.preventDefault();
        
        if (batchProcessor.isProcessing) {
            return; // Prevent multiple submissions
        }

        var $form = $(this);
        var $submitBtn = $('#mpbig-generate');
        var $spinner = $('#mpbig-spinner');
        var $progress = $('#mpbig-progress');
        var $progressText = $('#mpbig-progress-text');
        var $results = $('#mpbig-results');
        var $resultsContent = $('#mpbig-results-content');
        var $progressContainer = $('#mpbig-progress-container');

        // Clear any previous error messages
        $('.mpbig-notice-error').remove();

        // Validate form
        var validationErrors = validateForm();
        if (validationErrors.length > 0) {
            showValidationErrors(validationErrors);
            return;
        }

        // Clear any existing progress data and reset UI
        batchProcessor.isProcessing = false;
        stopProgressMonitoring();
        
        // Show loading state
        $submitBtn.prop('disabled', true).addClass('mpbig-loading');
        $spinner.removeClass('mpbig-hidden');
        $progress.removeClass('mpbig-hidden');
        $progressText.text(mpbig_ajax.generating);
        $results.addClass('mpbig-hidden');
        $progressContainer.addClass('mpbig-hidden');

        // Add loading animation to the form
        $form.addClass('mpbig-loading');

        // Collect form data
        var formData = new FormData($form[0]);
        formData.append('action', 'mpbig_generate_invoices');
        formData.append('nonce', mpbig_ajax.nonce);

        // Send initial AJAX request
        $.ajax({
            url: mpbig_ajax.ajax_url,
            type: 'POST',
            data: formData,
            processData: false,
            contentType: false,
            success: function(response) {
                if (response.success) {
                    // Start batch processing
                    batchProcessor.isProcessing = true;
                    batchProcessor.totalBatches = Math.ceil(response.total / mpbig_ajax.batch_size);
                    batchProcessor.currentBatch = 0;
                    
                    // Show progress container with animation
                    $progressContainer.removeClass('mpbig-hidden').addClass('mpbig-slide-down mpbig-fade-in');
                    // Force reflow to trigger animation
                    $progressContainer[0].offsetHeight;
                    $progressContainer.addClass('mpbig-visible');
                    updateProgressBar(0, response.total);
                    
                    // Start progress monitoring
                    startProgressMonitoring();
                    
                    // Process first batch
                    processBatch();
                } else {
                    showError(response.message);
                }
            },
            error: function(xhr, status, error) {
                var errorMessage = mpbig_ajax.error + ': ' + error;
                
                // Try to get more specific error message from response
                if (xhr.responseJSON && xhr.responseJSON.data && xhr.responseJSON.data.message) {
                    errorMessage = xhr.responseJSON.data.message;
                } else if (xhr.responseText) {
                    try {
                        var response = JSON.parse(xhr.responseText);
                        if (response.data && response.data.message) {
                            errorMessage = response.data.message;
                        }
                    } catch (e) {
                        // Use default error message
                    }
                }
                
                showError(errorMessage);
            }
        });
    });

    // Show error message
    function showError(message) {
        var $submitBtn = $('#mpbig-generate');
        var $spinner = $('#mpbig-spinner');
        var $progress = $('#mpbig-progress');
        var $results = $('#mpbig-results');
        var $resultsContent = $('#mpbig-results-content');
        var $form = $('#mpbig-form');
        var $progressContainer = $('#mpbig-progress-container');

        // Reset all processing states
        batchProcessor.isProcessing = false;
        stopProgressMonitoring();

        $resultsContent.html('<div class="mpbig-notice mpbig-notice-error"><p>' + message + '</p></div>');
        $results.removeClass('mpbig-hidden').addClass('mpbig-slide-down mpbig-fade-in');
        // Force reflow to trigger animation
        $results[0].offsetHeight;
        $results.addClass('mpbig-visible');
        
        // Reset button state
        $submitBtn.prop('disabled', false).removeClass('mpbig-loading');
        $spinner.addClass('mpbig-hidden');
        $progress.addClass('mpbig-hidden');
        $progressContainer.addClass('mpbig-hidden');
        $form.removeClass('mpbig-loading');
    }

    // Process a batch of transactions with enhanced feedback
    function processBatch() {
        var $submitBtn = $('#mpbig-generate');
        var $spinner = $('#mpbig-spinner');
        var $progressText = $('#mpbig-progress-text');
        var $results = $('#mpbig-results');
        var $resultsContent = $('#mpbig-results-content');
        var $progressContainer = $('#mpbig-progress-container');
        var $form = $('#mpbig-form');

        var formData = new FormData();
        formData.append('action', 'mpbig_process_batch');
        formData.append('nonce', mpbig_ajax.nonce);

        $.ajax({
            url: mpbig_ajax.ajax_url,
            type: 'POST',
            data: formData,
            processData: false,
            contentType: false,
            success: function(response) {
                if (response.success) {
                    batchProcessor.currentBatch++;
                    
                    if (response.complete) {
                        // Processing complete
                        batchProcessor.isProcessing = false;
                        stopProgressMonitoring();
                        
                        var message = '<div class="mpbig-notice mpbig-notice-success"><p>' + response.message + '</p></div>';
                        
                        if (response.errors && response.errors.length > 0) {
                            message += '<div class="mpbig-notice mpbig-notice-warning"><p><strong>Errors:</strong></p><ul>';
                            response.errors.forEach(function(error) {
                                message += '<li>' + error + '</li>';
                            });
                            message += '</ul></div>';
                        }
                        
                        $resultsContent.html(message);
                        $results.removeClass('mpbig-hidden').addClass('mpbig-slide-down mpbig-fade-in');
                        // Force reflow to trigger animation
                        $results[0].offsetHeight;
                        $results.addClass('mpbig-visible');
                        
                        // Reset button state
                        $submitBtn.prop('disabled', false).removeClass('mpbig-loading');
                        $spinner.addClass('mpbig-hidden');
                        $('#mpbig-progress').addClass('mpbig-hidden');
                        $progressContainer.removeClass('mpbig-visible').addClass('mpbig-slide-up');
                        setTimeout(function() {
                            $progressContainer.addClass('mpbig-hidden').removeClass('mpbig-slide-down mpbig-slide-up');
                        }, 400);
                        $form.removeClass('mpbig-loading');
                        
                        // Create ZIP file if requested
                        if (response.create_zip) {
                            createZipFile();
                        }
                    } else {
                        // Continue with next batch
                        setTimeout(processBatch, 100); // Small delay to prevent overwhelming the server
                    }
                } else {
                    // Error occurred
                    batchProcessor.isProcessing = false;
                    stopProgressMonitoring();
                    showError(response.message);
                }
            },
            error: function(xhr, status, error) {
                batchProcessor.isProcessing = false;
                stopProgressMonitoring();
                showError(mpbig_ajax.error + ': ' + error);
            }
        });
    }

    // Create ZIP file with enhanced UX
    function createZipFile() {
        var $resultsContent = $('#mpbig-results-content');
        var $progressText = $('#mpbig-progress-text');
        var $progress = $('#mpbig-progress');
        var $spinner = $('#mpbig-spinner');
        
        $progressText.text(mpbig_ajax.creating_zip);
        $progress.removeClass('mpbig-hidden');
        $spinner.removeClass('mpbig-hidden');

        var formData = new FormData();
        formData.append('action', 'mpbig_create_zip');
        formData.append('nonce', mpbig_ajax.nonce);

        $.ajax({
            url: mpbig_ajax.ajax_url,
            type: 'POST',
            data: formData,
            processData: false,
            contentType: false,
            success: function(response) {
                $progress.addClass('mpbig-hidden');
                $spinner.addClass('mpbig-hidden');
                
                if (response.success) {
                    var zipMessage = '<div class="mpbig-notice mpbig-notice-success"><p>' + response.message + '</p>';
                    zipMessage += '<p><a href="' + response.zip_url + '" class="mpbig-download-btn" download="' + response.zip_filename + '">Download ZIP File</a></p></div>';
                    
                    $resultsContent.append(zipMessage);
                } else {
                    var zipMessage = '<div class="mpbig-notice mpbig-notice-error"><p>' + response.message + '</p></div>';
                    $resultsContent.append(zipMessage);
                }
            },
            error: function(xhr, status, error) {
                $progress.addClass('mpbig-hidden');
                $spinner.addClass('mpbig-hidden');
                var zipMessage = '<div class="mpbig-notice mpbig-notice-error"><p>' + mpbig_ajax.zip_error + ': ' + error + '</p></div>';
                $resultsContent.append(zipMessage);
            }
        });
    }

    // Start progress monitoring with enhanced updates
    function startProgressMonitoring() {
        batchProcessor.progressInterval = setInterval(function() {
            updateProgressFromServer();
        }, 2000); // Update every 2 seconds
    }

    // Stop progress monitoring
    function stopProgressMonitoring() {
        if (batchProcessor.progressInterval) {
            clearInterval(batchProcessor.progressInterval);
            batchProcessor.progressInterval = null;
        }
    }

    // Update progress from server with smooth animations
    function updateProgressFromServer() {
        var formData = new FormData();
        formData.append('action', 'mpbig_get_progress');
        formData.append('nonce', mpbig_ajax.nonce);

        $.ajax({
            url: mpbig_ajax.ajax_url,
            type: 'POST',
            data: formData,
            processData: false,
            contentType: false,
            success: function(response) {
                if (response.success) {
                    updateProgressBar(response.processed, response.total);
                    updateProgressStatus(response.processed, response.total, response.successful, response.errors);
                }
            }
        });
    }

    // Update progress bar with smooth animation
    function updateProgressBar(processed, total) {
        var percentage = total > 0 ? Math.round((processed / total) * 100) : 0;
        
        // Use CSS custom property for dynamic width
        $('#mpbig-progress-fill')[0].style.setProperty('--progress-width', percentage + '%');
        $('#mpbig-progress-current').text(processed);
        $('#mpbig-progress-total').text(total);
        $('#mpbig-progress-percentage').text(percentage + '%');
    }

    // Update progress status with enhanced information
    function updateProgressStatus(processed, total, successful, errors) {
        var status = 'Processed: ' + processed + ' / ' + total + ' transactions';
        if (successful > 0) {
            status += ' | Successful: ' + successful;
        }
        if (errors && errors.length > 0) {
            status += ' | Errors: ' + errors.length;
        }
        
        $('#mpbig-progress-status').text(status);
    }

    // Enhanced checkbox interactions
    $('.mpbig-checkbox-item').on('click', function() {
        var $checkbox = $(this).find('input[type="checkbox"]');
        $checkbox.prop('checked', !$checkbox.prop('checked'));
        $(this).toggleClass('mpbig-checked', $checkbox.prop('checked'));
    });

    // Set default dates for current year
    var currentYear = new Date().getFullYear();
    $('#mpbig-start-date').val(currentYear + '-01-01');
    $('#mpbig-end-date').val(currentYear + '-12-31');

    // Add hover effects and animations
    $('.mpbig-card').hover(
        function() {
            $(this).addClass('mpbig-hover');
        },
        function() {
            $(this).removeClass('mpbig-hover');
        }
    );

    // Enhanced form field focus effects - only for non-select elements
    $('.mpbig-form-control:not(.mpbig-select)').on('focus', function() {
        $(this).closest('.mpbig-form-group').addClass('mpbig-focused');
    }).on('blur', function() {
        $(this).closest('.mpbig-form-group').removeClass('mpbig-focused');
    });

    // Download files functionality
    $('#mpbig-download-files').on('click', function() {
        var $button = $(this);
        var $spinner = $('#mpbig-download-spinner');
        
        // Show loading state
        $button.prop('disabled', true).addClass('mpbig-loading');
        $spinner.removeClass('mpbig-hidden');

        var formData = new FormData();
        formData.append('action', 'mpbig_download_files');
        formData.append('nonce', mpbig_ajax.nonce);

        $.ajax({
            url: mpbig_ajax.ajax_url,
            type: 'POST',
            data: formData,
            processData: false,
            contentType: false,
            success: function(response) {
                $button.prop('disabled', false).removeClass('mpbig-loading');
                $spinner.addClass('mpbig-hidden');
                
                if (response.success) {
                    // Create download link and trigger download
                    var link = document.createElement('a');
                    link.href = response.zip_url;
                    link.download = response.zip_filename;
                    document.body.appendChild(link);
                    link.click();
                    document.body.removeChild(link);
                    
                    // Show success message
                    var message = '<div class="mpbig-notice mpbig-notice-success"><p>' + response.message + ' (' + response.file_count + ' files)</p></div>';
                    $('.mpbig-file-management').after(message);
                    
                    // Scroll to message
                    $('html, body').animate({
                        scrollTop: $('.mpbig-notice-success').offset().top - 100
                    }, 500);
                    
                    // Remove message after 5 seconds
                    setTimeout(function() {
                        $('.mpbig-notice-success').addClass('mpbig-fade-out mpbig-hidden');
                        setTimeout(function() {
                            $('.mpbig-notice-success').remove();
                        }, 300);
                    }, 5000);
                } else {
                    // Show error message
                    var message = '<div class="mpbig-notice mpbig-notice-error"><p>' + response.message + '</p></div>';
                    $('.mpbig-file-management').after(message);
                    
                    // Scroll to message
                    $('html, body').animate({
                        scrollTop: $('.mpbig-notice-error').offset().top - 100
                    }, 500);
                }
            },
            error: function(xhr, status, error) {
                $button.prop('disabled', false).removeClass('mpbig-loading');
                $spinner.addClass('mpbig-hidden');
                
                var message = '<div class="mpbig-notice mpbig-notice-error"><p>Error: ' + error + '</p></div>';
                $('.mpbig-file-management').after(message);
            }
        });
    });

    // Empty folder functionality
    $('#mpbig-empty-folder').on('click', function() {
        if (!confirm('Are you sure you want to delete all PDF files? This action cannot be undone.')) {
            return;
        }

        var $button = $(this);
        var $spinner = $('#mpbig-empty-spinner');
        
        // Show loading state
        $button.prop('disabled', true).addClass('mpbig-loading');
        $spinner.removeClass('mpbig-hidden');

        var formData = new FormData();
        formData.append('action', 'mpbig_empty_folder');
        formData.append('nonce', mpbig_ajax.nonce);

        $.ajax({
            url: mpbig_ajax.ajax_url,
            type: 'POST',
            data: formData,
            processData: false,
            contentType: false,
            success: function(response) {
                $button.prop('disabled', false).removeClass('mpbig-loading');
                $spinner.addClass('mpbig-hidden');
                
                if (response.success) {
                    // Show success message
                    var message = '<div class="mpbig-notice mpbig-notice-success"><p>' + response.message + '</p></div>';
                    $('.mpbig-file-management').after(message);
                    
                    // Update file stats
                    $('.mpbig-file-stat-number').first().text('0');
                    $('.mpbig-file-stat-number').last().text('0 B');
                    
                    // Scroll to message
                    $('html, body').animate({
                        scrollTop: $('.mpbig-notice-success').offset().top - 100
                    }, 500);
                    
                    // Remove message after 5 seconds
                    setTimeout(function() {
                        $('.mpbig-notice-success').addClass('mpbig-fade-out mpbig-hidden');
                        setTimeout(function() {
                            $('.mpbig-notice-success').remove();
                        }, 300);
                    }, 5000);
                } else {
                    // Show error message
                    var message = '<div class="mpbig-notice mpbig-notice-error"><p>' + response.message + '</p></div>';
                    $('.mpbig-file-management').after(message);
                    
                    // Scroll to message
                    $('html, body').animate({
                        scrollTop: $('.mpbig-notice-error').offset().top - 100
                    }, 500);
                }
            },
            error: function(xhr, status, error) {
                $button.prop('disabled', false).removeClass('mpbig-loading');
                $spinner.addClass('mpbig-hidden');
                
                var message = '<div class="mpbig-notice mpbig-notice-error"><p>Error: ' + error + '</p></div>';
                $('.mpbig-file-management').after(message);
            }
        });
    });

    // Add loading animation to the page
    $(window).on('load', function() {
        $('.mpbig-container').addClass('mpbig-loaded');
    });
});
