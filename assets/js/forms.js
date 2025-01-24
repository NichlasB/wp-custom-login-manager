jQuery(document).ready(function($) {
    'use strict';

    const WPCLM = {
        init: function() {
            this.bindEvents();
            this.initPasswordStrengthMeter();
            this.initKeyboardNavigation();
        },

        bindEvents: function() {
            $('.wpclm-form form').on('submit', this.handleFormSubmit);
            $('input[type="password"]').on('input', function() {
                WPCLM.checkPasswordStrength.call(this);
                WPCLM.validatePasswordRequirements(this.value);
            });
            
            // Add aria-invalid on invalid fields
            $('input').on('invalid', function() {
                $(this).attr('aria-invalid', 'true');
            }).on('input', function() {
                $(this).removeAttr('aria-invalid');
            });
        },

        initKeyboardNavigation: function() {
            // Handle enter key on buttons
            $('.wpclm-button').on('keydown', function(e) {
                if (e.key === 'Enter' || e.key === ' ') {
                    e.preventDefault();
                    $(this).click();
                }
            });

            // Trap focus in modal dialogs
            $('.wpclm-modal').on('keydown', function(e) {
                if (e.key === 'Tab') {
                    const focusableElements = $(this).find(
                        'button, [href], input, select, textarea, [tabindex]:not([tabindex="-1"])'
                    ).filter(':visible');
                    
                    const firstFocusableElement = focusableElements.first();
                    const lastFocusableElement = focusableElements.last();

                    if (e.shiftKey) {
                        if (document.activeElement === firstFocusableElement[0]) {
                            lastFocusableElement.focus();
                            e.preventDefault();
                        }
                    } else {
                        if (document.activeElement === lastFocusableElement[0]) {
                            firstFocusableElement.focus();
                            e.preventDefault();
                        }
                    }
                }
            });

            // Close modals with escape key
            $(document).on('keydown', function(e) {
                if (e.key === 'Escape') {
                    $('.wpclm-modal').hide();
                    // Return focus to the element that opened the modal
                    const $opener = $('[data-modal-opener]');
                    if ($opener.length) {
                        $opener.focus();
                    }
                }
            });

            // Manage focus when showing error messages
            this.handleErrorFocus();
        },

        handleErrorFocus: function() {
            // When error messages are shown, set focus to the first invalid field
            const observer = new MutationObserver((mutations) => {
                mutations.forEach((mutation) => {
                    if (mutation.addedNodes.length) {
                        const $errorMessage = $(mutation.addedNodes).filter('.wpclm-error-message');
                        if ($errorMessage.length) {
                            const $invalidInput = $errorMessage.closest('.form-field').find('input');
                            if ($invalidInput.length) {
                                $invalidInput.focus();
                            }
                        }
                    }
                });
            });

            observer.observe(document.body, {
                childList: true,
                subtree: true
            });
        },

        handleFormSubmit: function(e) {
            const $form = $(this);
            const $submitButton = $form.find('.wpclm-button');
            
            // Clear previous errors
            $('.wpclm-error-message').remove();
            
            // Basic validation
            if (!WPCLM.validateForm($form)) {
                e.preventDefault();
                return false;
            }

            // Add loading state
            $form.addClass('loading');
            $submitButton.prop('disabled', true)
                        .attr('aria-busy', 'true');
        },

        validateForm: function($form) {
            let isValid = true;
            const messages = wpclm_forms.messages;

            // Check required fields
            $form.find('[required]').each(function() {
                if (!$(this).val()) {
                    WPCLM.showError($(this), messages.required);
                    $(this).attr('aria-invalid', 'true');
                    isValid = false;
                }
            });

            // Validate email
            const $email = $form.find('input[type="email"]');
            if ($email.length && $email.val() && !WPCLM.isValidEmail($email.val())) {
                WPCLM.showError($email, messages.email);
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