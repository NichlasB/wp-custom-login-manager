jQuery(document).ready(function($) {
    'use strict';

    // Global callback for Cloudflare Turnstile
    window.onTurnstileCallback = function(token) {
        console.log('Turnstile token received:', token.substring(0, 10) + '...');
        
        // Create a hidden field for the token if it doesn't exist
        const $form = $('.cf-turnstile').closest('form');
        if ($form.find('input[name="cf-turnstile-response"]').length === 0) {
            $form.append('<input type="hidden" name="cf-turnstile-response" value="' + token + '">');
            console.log('Created hidden input field for Turnstile token');
        } else {
            // Update existing hidden field
            $form.find('input[name="cf-turnstile-response"]').val(token);
            console.log('Updated existing hidden input field with Turnstile token');
        }
        
        // Enable submit buttons
        $form.find('.wpclm-button').prop('disabled', false).removeClass('wpclm-button-disabled');
        $('.turnstile-loading').fadeOut();
    };

    const WPCLM = {
        // Track Turnstile initialization status
        turnstileReady: false,
        
        init: function() {
            this.bindEvents();
            this.initPasswordStrengthMeter();
            this.initKeyboardNavigation();
            this.initTurnstile();
            this.initCheckoutRedirect();
        },
        
        // Initialize checkout redirect functionality
        initCheckoutRedirect: function() {
            // Check if we have a checkout redirect message
            if ($('.wpclm-checkout-redirect').length) {
                // Hide the welcome message
                $('.wpclm-welcome-message').hide();
                
                // Optionally, you can add some animation
                $('.wpclm-message').fadeIn(300);
            }
        },
        
        // Helper function to check if Turnstile token exists and enable button
        checkTokenAndEnableButton: function($submitBtn) {
            // Check if token exists
            const token = $('input[name="cf-turnstile-response"]').val();
            if (token && token.length > 0) {
                console.log('Turnstile token found, enabling submit button');
                WPCLM.turnstileReady = true;
                $submitBtn.prop('disabled', false);
                $submitBtn.removeClass('wpclm-button-disabled');
                $('.turnstile-loading').fadeOut();
                return true;
            }
            return false;
        },
        
        /**
         * Initialize Turnstile widget
         * 
         * IMPORTANT: Turnstile integration is intentionally disabled for login and password reset forms.
         * This is a deliberate design decision to improve user experience and prevent security verification errors.
         * 
         * The integration works as follows:
         * 1. On registration forms: Turnstile is fully enabled and required
         * 2. On login forms: Turnstile is completely disabled (both frontend and backend)
         * 3. On password reset forms: Turnstile is completely disabled (both frontend and backend)
         *
         * The backend PHP code in WPCLM_Turnstile::is_enabled_for() is hardcoded to return false
         * for login and reset forms regardless of settings.
         */
        initTurnstile: function() {
            // Check if Turnstile widget exists on the page
            if ($('.cf-turnstile').length > 0) {
                // Skip if we're on the login form
                if ($('#wpclm-login-form').length > 0) {
                    console.log('Login form detected - skipping Turnstile initialization');
                    // Make sure the submit button is enabled
                    $('#wpclm-login-form').find('.wpclm-button').prop('disabled', false).removeClass('wpclm-button-disabled');
                    return;
                }
                
                // Identify which form we're on
                const isResetForm = $('#wpclm-lostpass-form, #wpclm-resetpass-form').length > 0;
                const formType = isResetForm ? 'reset password' : 'registration';
                console.log('Turnstile widget found on ' + formType + ' form');
                
                // Add loading message
                $('.cf-turnstile').before('<p class="turnstile-loading">Security verification loading...</p>');
                
                // Initially disable submit button until Turnstile is ready
                const $form = $('.cf-turnstile').closest('form');
                const $submitBtn = $form.find('button[type="submit"]');
                $submitBtn.prop('disabled', true).addClass('wpclm-button-disabled');
                
                // Create a hidden input field for the token if it doesn't exist
                if ($form.find('input[name="cf-turnstile-response"]').length === 0) {
                    $form.append('<input type="hidden" name="cf-turnstile-response" value="">');
                    console.log('Added hidden input field for Turnstile token');
                }
                
                // Set up explicit callback for existing Turnstile widget
                if (typeof turnstile !== 'undefined') {
                    try {
                        // We don't need to render a new widget, just make sure the callback is set
                        // This prevents duplicate widgets
                        window.turnstileOptions = {
                            callback: 'onTurnstileCallback'
                        };
                        
                        console.log('Turnstile callback configured');
                    } catch (e) {
                        console.error('Error configuring Turnstile:', e);
                    }
                }
                
                // Listen for the custom turnstile_ready event
                $(document).on('turnstile_ready', function() {
                    console.log('Turnstile is ready');
                    
                    // Wait a short moment for token to be populated
                    setTimeout(function() {
                        if (!WPCLM.checkTokenAndEnableButton($submitBtn)) {
                            // If token still not found, start polling
                            console.log('Token not found after ready event, starting polling');
                            const tokenCheckInterval = setInterval(function() {
                                if (WPCLM.checkTokenAndEnableButton($submitBtn)) {
                                    clearInterval(tokenCheckInterval);
                                }
                            }, 500); // Check every 500ms
                            
                            // Stop checking after 5 seconds
                            setTimeout(function() {
                                clearInterval(tokenCheckInterval);
                                // Enable button anyway as fallback
                                if (!WPCLM.turnstileReady) {
                                    console.log('Enabling button as fallback after polling');
                                    WPCLM.turnstileReady = true;
                                    $submitBtn.prop('disabled', false);
                                    $submitBtn.removeClass('wpclm-button-disabled');
                                    $('.turnstile-loading').fadeOut();
                                }
                            }, 5000);
                        }
                    }, 300);
                });
                
                // Fallback in case the event doesn't fire
                setTimeout(function() {
                    if (!WPCLM.turnstileReady) {
                        console.log('Turnstile ready event never fired, using fallback');
                        // Enable button anyway
                        WPCLM.turnstileReady = true;
                        $submitBtn.prop('disabled', false);
                        $submitBtn.removeClass('wpclm-button-disabled');
                        $('.turnstile-loading').fadeOut();
                    }
                }, 5000); // 5 second fallback
            }
        },

        /**
         * Bind events to form elements
         */
        bindEvents: function() {
            $('.wpclm-form form').on('submit', this.handleFormSubmit);
            $('input[type="password"]').on('input', function() {
                WPCLM.checkPasswordStrength.call(this);
            });
            
            $('#pass1').on('input', function() {
                WPCLM.validatePasswordRequirements($(this).val());
            });
            
            $('input').on('invalid', function() {
                $(this).attr('aria-invalid', 'true');
            }).on('input', function() {
                $(this).removeAttr('aria-invalid');
            });
        },
        
        /**
         * Handle form submission
         * 
         * This function handles the submission of all forms (login, registration, password reset)
         * with special handling for Turnstile verification:
         * 
         * - Login forms: Skip all Turnstile processing completely
         * - Registration forms: Require valid Turnstile token
         * - Password reset forms: Skip Turnstile processing (handled by backend)
         * 
         * @param {Event} e - The form submission event
         * @returns {boolean} - Whether to allow the form submission to proceed
         */
        handleFormSubmit: function(e) {
            const $form = $(this);
            const $submitButton = $form.find('.wpclm-button');
            console.log('Form submission started:', $form.attr('id'));
            
            // Clear previous errors
            $('.wpclm-error-message').remove();
            
            // Skip all Turnstile processing for login form
            // This is intentional to prevent security verification errors on login
            if ($form.attr('id') === 'wpclm-login-form') {
                console.log('Login form detected - skipping Turnstile verification');
                // Basic validation only for login form
                if (!WPCLM.validateForm($form)) {
                    e.preventDefault();
                    return false;
                }
                // Allow login form to submit normally
                return true;
            }
            
            // For non-login forms, check if Turnstile is present
            if ($form.find('.cf-turnstile').length > 0) {
                // Non-login form with Turnstile
                console.log('Processing form submission with Turnstile:', $form.attr('id'));
                
                // Check for Turnstile token
                let turnstileResponse = $form.find('input[name="cf-turnstile-response"]').val();
                
                // If no token in the form, check if Turnstile is available and try to refresh the token
                if (!turnstileResponse && typeof turnstile !== 'undefined') {
                    try {
                        // Force a token refresh but don't re-render
                        turnstile.reset();
                        
                        console.log('Refreshed Turnstile widget, waiting for token...');
                        
                        // Give a small delay to allow the token to be generated
                        setTimeout(function() {
                            // Check if we have a token now
                            turnstileResponse = $form.find('input[name="cf-turnstile-response"]').val();
                            if (turnstileResponse) {
                                console.log('Successfully retrieved Turnstile token after reset');
                                $form.submit();
                            }
                        }, 1000);
                    } catch (e) {
                        console.error('Error refreshing Turnstile token:', e);
                    }
                }
                
                // Final check for token
                turnstileResponse = $form.find('input[name="cf-turnstile-response"]').val();
                console.log('Final Turnstile token check in form:', $form.attr('id'), 'Token exists:', !!turnstileResponse);
                if (!turnstileResponse) {
                    e.preventDefault();
                    // Try to reset the Turnstile widget if available
                    if (typeof turnstile !== 'undefined') {
                        try {
                            turnstile.reset();
                            console.log('Turnstile widget reset due to missing token');
                        } catch (err) {
                            console.error('Failed to reset Turnstile widget', err);
                        }
                    }
                    WPCLM.showError($form.find('.cf-turnstile'), 'Please complete the security verification before submitting.');
                    return false;
                }
            }
            
            // Validate form fields
            if (!WPCLM.validateForm($form)) {
                e.preventDefault();
                return false;
            }
            
            // Add loading state
            $form.addClass('loading');
            $submitButton.prop('disabled', true).attr('aria-busy', 'true');
        },
        
        /**
         * Validate form fields
         * 
         * Performs validation on form fields including:
         * - Required fields
         * - Email format
         * - Password requirements and matching
         * 
         * @param {jQuery} $form - The form jQuery object to validate
         * @returns {boolean} - Whether the form is valid
         */
        validateForm: function($form) {
            let isValid = true;
            const messages = wpclm_forms.messages;

            // Check required fields
            $form.find('[required]').each(function() {
                if (!$(this).val()) {
                    const fieldName = $(this).attr('name') || 'Field';
                    WPCLM.showError($(this), messages.required_field || fieldName + ' is required');
                    $(this).attr('aria-invalid', 'true');
                    isValid = false;
                }
            });

            // Check email format
            const $email = $form.find('input[type="email"]');
            if ($email.length && $email.val() && !WPCLM.isValidEmail($email.val())) {
                WPCLM.showError($email, messages.invalid_email || 'Please enter a valid email address');
                $email.attr('aria-invalid', 'true');
                isValid = false;
            }
            
            // Check password match and requirements if confirming password
            const $pass1 = $form.find('#pass1');
            const $pass2 = $form.find('#pass2');
            if ($pass1.length && $pass2.length) {
                const password = $pass1.val();
                
                // Check password requirements
                if (!WPCLM.validatePasswordRequirements(password)) {
                    if (password.length < 12) {
                        WPCLM.showError($pass1, messages.password_length || 'Password must be at least 12 characters long.');
                    } else if (!/[A-Z]/.test(password)) {
                        WPCLM.showError($pass1, messages.password_uppercase || 'Password must include at least one uppercase letter.');
                    } else if (!/[a-z]/.test(password)) {
                        WPCLM.showError($pass1, messages.password_lowercase || 'Password must include at least one lowercase letter.');
                    } else if (!/[0-9]/.test(password)) {
                        WPCLM.showError($pass1, messages.password_number || 'Password must include at least one number.');
                    }
                    $pass1.attr('aria-invalid', 'true');
                    isValid = false;
                }
                
                // Check if passwords match
                if ($pass1.val() !== $pass2.val()) {
                    WPCLM.showError($pass2, messages.password_match);
                    $pass2.attr('aria-invalid', 'true');
                    isValid = false;
                }
            }

            return isValid;
        },

        showError: function($element, message) {
            const $error = $('<div class="wpclm-error-message" role="alert">' + message + '</div>');
            $element.closest('.form-field').append($error);
        },

        isValidEmail: function(email) {
            const re = /^(([^<>()\[\]\\.,;:\s@"]+(\.[^<>()\[\]\\.,;:\s@"]+)*)|(".+"))@((\[[0-9]{1,3}\.[0-9]{1,3}\.[0-9]{1,3}\.[0-9]{1,3}])|(([a-zA-Z\-0-9]+\.)+[a-zA-Z]{2,}))$/;
            return re.test(email.toLowerCase());
        },

        initPasswordStrengthMeter: function() {
            const $pass1 = $('#pass1');
            if (!$pass1.length) return;

            const $meter = $pass1.closest('.form-field').find('.password-strength-meter');
            
            $pass1.on('input', function() {
                const password = $(this).val();
                const strength = WPCLM.checkPasswordStrength(password);
                
                $meter.removeClass('very-weak weak medium strong')
                      .addClass(strength)
                      .attr('aria-label', 'Password strength: ' + strength);
            });
        },

        checkPasswordStrength: function(password) {
            if (!password) return 'very-weak';

            let strength = 0;
            
            // Length check
            if (password.length >= 8) strength++;
            
            // Contains lowercase letters
            if (password.match(/[a-z]/)) strength++;
            
            // Contains uppercase letters
            if (password.match(/[A-Z]/)) strength++;
            
            // Contains numbers
            if (password.match(/\d/)) strength++;
            
            // Contains special characters
            if (password.match(/[^a-zA-Z\d]/)) strength++;

            switch (strength) {
                case 0:
                case 1:
                    return 'very-weak';
                case 2:
                    return 'weak';
                case 3:
                case 4:
                    return 'medium';
                case 5:
                    return 'strong';
                default:
                    return 'strong';
            }
        },

        validatePasswordRequirements: function(password) {
            const requirements = {
                length: password.length >= 12,
                uppercase: /[A-Z]/.test(password),
                lowercase: /[a-z]/.test(password),
                number: /[0-9]/.test(password)
            };
        
            // Update UI for each requirement
            Object.keys(requirements).forEach(req => {
                const $requirement = $('.requirement.' + req);
                if (requirements[req]) {
                    $requirement.addClass('valid');
                } else {
                    $requirement.removeClass('valid');
                }
            });
        
            // Return true if all requirements are met
            return Object.values(requirements).every(req => req === true);
        }
        
    };
    
    // Initialize
    WPCLM.init();
});