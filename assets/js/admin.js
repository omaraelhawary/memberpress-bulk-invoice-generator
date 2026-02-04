jQuery(document).ready(function($) {
    'use strict';

    var batchProcessor = {
        isProcessing: false,
        progressInterval: null,
        currentBatch: 0,
        totalBatches: 0
    };

    // Initialize date pickers with default WordPress styling
    $('.mpfig-datepicker').datepicker({
        dateFormat: 'yy-mm-dd',
        changeMonth: true,
        changeYear: true,
        yearRange: '-10:+1',
        showAnim: 'fadeIn'
    });

    // Show/hide period options with smooth animation
    $('#mpfig-type').on('change', function() {
        var type = $(this).val();
        var $periodOptions = $('#mpfig-period-options');
        if (type === 'period') {
            $periodOptions.removeClass('mpfig-hidden').addClass('mpfig-slide-down mpfig-visible mpfig-fade-in');
            // Force reflow to trigger animation
            $periodOptions[0].offsetHeight;
            $periodOptions.addClass('mpfig-visible');
        } else {
            $periodOptions.removeClass('mpfig-visible').addClass('mpfig-slide-up');
            setTimeout(function() {
                $periodOptions.addClass('mpfig-hidden').removeClass('mpfig-slide-down mpfig-slide-up');
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
        var type = $('#mpfig-type').val();
        if (type === 'period') {
            var startDate = $('#mpfig-start-date').val();
            var endDate = $('#mpfig-end-date').val();
            
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
        var customerEmail = $('#mpfig-customer-email').val().trim();
        if (customerEmail && !isValidEmail(customerEmail)) {
            errors.push('Please enter a valid email address for the customer filter.');
        }

        return errors;
    }

    // Show validation errors in a modern way
    function showValidationErrors(errors) {
        var errorHtml = '<div class="mpfig-notice mpfig-notice-error"><p><strong>Please fix the following errors:</strong></p><ul>';
        errors.forEach(function(error) {
            errorHtml += '<li>' + error + '</li>';
        });
        errorHtml += '</ul></div>';
        
        // Remove any existing error messages
        $('.mpfig-notice-error').remove();
        
        // Insert error message at the top of the form
        $('#mpfig-form').prepend(errorHtml);
        
        // Scroll to the error message
        $('html, body').animate({
            scrollTop: $('.mpfig-notice-error').offset().top - 100
        }, 500);
    }

    // Form submission with enhanced UX
    $('#mpfig-form').on('submit', function(e) {
        e.preventDefault();
        
        if (batchProcessor.isProcessing) {
            return; // Prevent multiple submissions
        }

        var $form = $(this);
        var $submitBtn = $('#mpfig-generate');
        var $spinner = $('#mpfig-spinner');
        var $progress = $('#mpfig-progress');
        var $progressText = $('#mpfig-progress-text');
        var $results = $('#mpfig-results');
        var $resultsContent = $('#mpfig-results-content');
        var $progressContainer = $('#mpfig-progress-container');

        // Clear any previous error messages
        $('.mpfig-notice-error').remove();

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
        $submitBtn.prop('disabled', true).addClass('mpfig-loading');
        $spinner.removeClass('mpfig-hidden');
        $progress.removeClass('mpfig-hidden');
        $progressText.text(mpfig_ajax.generating);
        $results.addClass('mpfig-hidden');
        $progressContainer.addClass('mpfig-hidden');

        // Add loading animation to the form
        $form.addClass('mpfig-loading');

        // Collect form data
        var formData = new FormData($form[0]);
        formData.append('action', 'mpfig_generate_invoices');
        formData.append('nonce', mpfig_ajax.nonce);

        // Send initial AJAX request
        $.ajax({
            url: mpfig_ajax.ajax_url,
            type: 'POST',
            data: formData,
            processData: false,
            contentType: false,
            success: function(response) {
                if (response.success) {
                    // Start batch processing
                    batchProcessor.isProcessing = true;
                    batchProcessor.totalBatches = Math.ceil(response.total / mpfig_ajax.batch_size);
                    batchProcessor.currentBatch = 0;
                    
                    // Show progress container with animation
                    $progressContainer.removeClass('mpfig-hidden').addClass('mpfig-slide-down mpfig-fade-in');
                    // Force reflow to trigger animation
                    $progressContainer[0].offsetHeight;
                    $progressContainer.addClass('mpfig-visible');
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
                var errorMessage = mpfig_ajax.error + ': ' + error;
                
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
        var $submitBtn = $('#mpfig-generate');
        var $spinner = $('#mpfig-spinner');
        var $progress = $('#mpfig-progress');
        var $results = $('#mpfig-results');
        var $resultsContent = $('#mpfig-results-content');
        var $form = $('#mpfig-form');
        var $progressContainer = $('#mpfig-progress-container');

        // Reset all processing states
        batchProcessor.isProcessing = false;
        stopProgressMonitoring();

        $resultsContent.html('<div class="mpfig-notice mpfig-notice-error"><p>' + message + '</p></div>');
        $results.removeClass('mpfig-hidden').addClass('mpfig-slide-down mpfig-fade-in');
        // Force reflow to trigger animation
        $results[0].offsetHeight;
        $results.addClass('mpfig-visible');
        
        // Reset button state
        $submitBtn.prop('disabled', false).removeClass('mpfig-loading');
        $spinner.addClass('mpfig-hidden');
        $progress.addClass('mpfig-hidden');
        $progressContainer.addClass('mpfig-hidden');
        $form.removeClass('mpfig-loading');
    }

    // Process a batch of transactions with enhanced feedback
    function processBatch() {
        var $submitBtn = $('#mpfig-generate');
        var $spinner = $('#mpfig-spinner');
        var $progressText = $('#mpfig-progress-text');
        var $results = $('#mpfig-results');
        var $resultsContent = $('#mpfig-results-content');
        var $progressContainer = $('#mpfig-progress-container');
        var $form = $('#mpfig-form');

        var formData = new FormData();
        formData.append('action', 'mpfig_process_batch');
        formData.append('nonce', mpfig_ajax.nonce);

        $.ajax({
            url: mpfig_ajax.ajax_url,
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
                        
                        var message = '<div class="mpfig-notice mpfig-notice-success"><p>' + response.message + '</p></div>';
                        
                        if (response.errors && response.errors.length > 0) {
                            message += '<div class="mpfig-notice mpfig-notice-warning"><p><strong>Errors:</strong></p><ul>';
                            response.errors.forEach(function(error) {
                                message += '<li>' + error + '</li>';
                            });
                            message += '</ul></div>';
                        }
                        
                        $resultsContent.html(message);
                        $results.removeClass('mpfig-hidden').addClass('mpfig-slide-down mpfig-fade-in');
                        // Force reflow to trigger animation
                        $results[0].offsetHeight;
                        $results.addClass('mpfig-visible');
                        
                        // Reset button state
                        $submitBtn.prop('disabled', false).removeClass('mpfig-loading');
                        $spinner.addClass('mpfig-hidden');
                        $('#mpfig-progress').addClass('mpfig-hidden');
                        $progressContainer.removeClass('mpfig-visible').addClass('mpfig-slide-up');
                        setTimeout(function() {
                            $progressContainer.addClass('mpfig-hidden').removeClass('mpfig-slide-down mpfig-slide-up');
                        }, 400);
                        $form.removeClass('mpfig-loading');
                        
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
                showError(mpfig_ajax.error + ': ' + error);
            }
        });
    }

    // Create ZIP file with enhanced UX
    function createZipFile() {
        var $resultsContent = $('#mpfig-results-content');
        var $progressText = $('#mpfig-progress-text');
        var $progress = $('#mpfig-progress');
        var $spinner = $('#mpfig-spinner');
        
        $progressText.text(mpfig_ajax.creating_zip);
        $progress.removeClass('mpfig-hidden');
        $spinner.removeClass('mpfig-hidden');

        var formData = new FormData();
        formData.append('action', 'mpfig_create_zip');
        formData.append('nonce', mpfig_ajax.nonce);

        $.ajax({
            url: mpfig_ajax.ajax_url,
            type: 'POST',
            data: formData,
            processData: false,
            contentType: false,
            success: function(response) {
                $progress.addClass('mpfig-hidden');
                $spinner.addClass('mpfig-hidden');
                
                if (response.success) {
                    var zipMessage = '<div class="mpfig-notice mpfig-notice-success"><p>' + response.message + '</p>';
                    zipMessage += '<p><a href="' + response.zip_url + '" class="mpfig-download-btn" download="' + response.zip_filename + '">Download ZIP File</a></p></div>';
                    
                    $resultsContent.append(zipMessage);
                } else {
                    var zipMessage = '<div class="mpfig-notice mpfig-notice-error"><p>' + response.message + '</p></div>';
                    $resultsContent.append(zipMessage);
                }
            },
            error: function(xhr, status, error) {
                $progress.addClass('mpfig-hidden');
                $spinner.addClass('mpfig-hidden');
                var zipMessage = '<div class="mpfig-notice mpfig-notice-error"><p>' + mpfig_ajax.zip_error + ': ' + error + '</p></div>';
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
        formData.append('action', 'mpfig_get_progress');
        formData.append('nonce', mpfig_ajax.nonce);

        $.ajax({
            url: mpfig_ajax.ajax_url,
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
        $('#mpfig-progress-fill')[0].style.setProperty('--progress-width', percentage + '%');
        $('#mpfig-progress-current').text(processed);
        $('#mpfig-progress-total').text(total);
        $('#mpfig-progress-percentage').text(percentage + '%');
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
        
        $('#mpfig-progress-status').text(status);
    }

    // Enhanced checkbox interactions
    $('.mpfig-checkbox-item').on('click', function() {
        var $checkbox = $(this).find('input[type="checkbox"]');
        $checkbox.prop('checked', !$checkbox.prop('checked'));
        $(this).toggleClass('mpfig-checked', $checkbox.prop('checked'));
    });

    // Set default dates for current year
    var currentYear = new Date().getFullYear();
    $('#mpfig-start-date').val(currentYear + '-01-01');
    $('#mpfig-end-date').val(currentYear + '-12-31');

    // Add hover effects and animations
    $('.mpfig-card').hover(
        function() {
            $(this).addClass('mpfig-hover');
        },
        function() {
            $(this).removeClass('mpfig-hover');
        }
    );

    // Enhanced form field focus effects - only for non-select elements
    $('.mpfig-form-control:not(.mpfig-select)').on('focus', function() {
        $(this).closest('.mpfig-form-group').addClass('mpfig-focused');
    }).on('blur', function() {
        $(this).closest('.mpfig-form-group').removeClass('mpfig-focused');
    });

    // Download files functionality
    $('#mpfig-download-files').on('click', function() {
        var $button = $(this);
        var $spinner = $('#mpfig-download-spinner');
        
        // Show loading state
        $button.prop('disabled', true).addClass('mpfig-loading');
        $spinner.removeClass('mpfig-hidden');

        var formData = new FormData();
        formData.append('action', 'mpfig_download_files');
        formData.append('nonce', mpfig_ajax.nonce);

        $.ajax({
            url: mpfig_ajax.ajax_url,
            type: 'POST',
            data: formData,
            processData: false,
            contentType: false,
            success: function(response) {
                $button.prop('disabled', false).removeClass('mpfig-loading');
                $spinner.addClass('mpfig-hidden');
                
                if (response.success) {
                    // Create download link and trigger download
                    var link = document.createElement('a');
                    link.href = response.zip_url;
                    link.download = response.zip_filename;
                    document.body.appendChild(link);
                    link.click();
                    document.body.removeChild(link);
                    
                    // Show success message
                    var message = '<div class="mpfig-notice mpfig-notice-success"><p>' + response.message + ' (' + response.file_count + ' files)</p></div>';
                    $('.mpfig-file-management').after(message);
                    
                    // Scroll to message
                    $('html, body').animate({
                        scrollTop: $('.mpfig-notice-success').offset().top - 100
                    }, 500);
                    
                    // Remove message after 5 seconds
                    setTimeout(function() {
                        $('.mpfig-notice-success').addClass('mpfig-fade-out mpfig-hidden');
                        setTimeout(function() {
                            $('.mpfig-notice-success').remove();
                        }, 300);
                    }, 5000);
                } else {
                    // Show error message
                    var message = '<div class="mpfig-notice mpfig-notice-error"><p>' + response.message + '</p></div>';
                    $('.mpfig-file-management').after(message);
                    
                    // Scroll to message
                    $('html, body').animate({
                        scrollTop: $('.mpfig-notice-error').offset().top - 100
                    }, 500);
                }
            },
            error: function(xhr, status, error) {
                $button.prop('disabled', false).removeClass('mpfig-loading');
                $spinner.addClass('mpfig-hidden');
                
                var message = '<div class="mpfig-notice mpfig-notice-error"><p>Error: ' + error + '</p></div>';
                $('.mpfig-file-management').after(message);
            }
        });
    });

    // Empty folder functionality
    $('#mpfig-empty-folder').on('click', function() {
        if (!confirm('Are you sure you want to delete all PDF files? This action cannot be undone.')) {
            return;
        }

        var $button = $(this);
        var $spinner = $('#mpfig-empty-spinner');
        
        // Show loading state
        $button.prop('disabled', true).addClass('mpfig-loading');
        $spinner.removeClass('mpfig-hidden');

        var formData = new FormData();
        formData.append('action', 'mpfig_empty_folder');
        formData.append('nonce', mpfig_ajax.nonce);

        $.ajax({
            url: mpfig_ajax.ajax_url,
            type: 'POST',
            data: formData,
            processData: false,
            contentType: false,
            success: function(response) {
                $button.prop('disabled', false).removeClass('mpfig-loading');
                $spinner.addClass('mpfig-hidden');
                
                if (response.success) {
                    // Show success message
                    var message = '<div class="mpfig-notice mpfig-notice-success"><p>' + response.message + '</p></div>';
                    $('.mpfig-file-management').after(message);
                    
                    // Update file stats
                    $('.mpfig-file-stat-number').first().text('0');
                    $('.mpfig-file-stat-number').last().text('0 B');
                    
                    // Scroll to message
                    $('html, body').animate({
                        scrollTop: $('.mpfig-notice-success').offset().top - 100
                    }, 500);
                    
                    // Remove message after 5 seconds
                    setTimeout(function() {
                        $('.mpfig-notice-success').addClass('mpfig-fade-out mpfig-hidden');
                        setTimeout(function() {
                            $('.mpfig-notice-success').remove();
                        }, 300);
                    }, 5000);
                } else {
                    // Show error message
                    var message = '<div class="mpfig-notice mpfig-notice-error"><p>' + response.message + '</p></div>';
                    $('.mpfig-file-management').after(message);
                    
                    // Scroll to message
                    $('html, body').animate({
                        scrollTop: $('.mpfig-notice-error').offset().top - 100
                    }, 500);
                }
            },
            error: function(xhr, status, error) {
                $button.prop('disabled', false).removeClass('mpfig-loading');
                $spinner.addClass('mpfig-hidden');
                
                var message = '<div class="mpfig-notice mpfig-notice-error"><p>Error: ' + error + '</p></div>';
                $('.mpfig-file-management').after(message);
            }
        });
    });

    // Add loading animation to the page
    $(window).on('load', function() {
        $('.mpfig-container').addClass('mpfig-loaded');
    });
});
