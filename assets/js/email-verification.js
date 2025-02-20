(function($) {
    'use strict';

    const clearMessages = () => {
        const messages = document.querySelectorAll('.wpclm-email-validation');
        messages.forEach(msg => msg.innerHTML = '');
    };

    let emailTimeout = null;
    let isEmailValid = false;
    const emailInput = document.querySelector('.wpclm-register-form input[name="user_email"]');
    const submitButton = document.querySelector('.wpclm-register-form .form-field.submit-button button');
    
    if (!emailInput || !submitButton) return;

    // Disable submit button by default
    submitButton.disabled = true;

    // Prevent form submission if email is invalid
    const form = document.querySelector('#wpclm-register-form');
    if (form) {
        form.addEventListener('submit', function(e) {
            if (!isEmailValid) {
                e.preventDefault();
                validationUI.className = 'wpclm-email-validation error';
                validationUI.innerHTML = '<span class="dashicons dashicons-no"></span> ' + 
                                    'Please wait for email verification to complete';
            }
        });
    }

    // Add validation UI elements
    const validationUI = document.createElement('div');
    validationUI.className = 'wpclm-email-validation';
    emailInput.parentNode.appendChild(validationUI);

    // Add styles
    const style = document.createElement('style');
    style.textContent = `
        .wpclm-email-validation {
            margin-top: 8px;
            font-size: 0.9em;
            min-height: 24px;
        }
        .wpclm-email-validation.loading {
            color: #666;
        }
        .wpclm-email-validation.success {
            color: #28a745;
        }
        .wpclm-email-validation.error {
            color: #dc3545;
        }
        .wpclm-email-spinner {
            display: inline-block;
            width: 20px;
            height: 20px;
            border: 2px solid #f3f3f3;
            border-top: 2px solid #3498db;
            border-radius: 50%;
            animation: wpclm-spin 1s linear infinite;
            margin-right: 8px;
            vertical-align: middle;
        }
        @keyframes wpclm-spin {
            0% { transform: rotate(0deg); }
            100% { transform: rotate(360deg); }
        }
    `;
    document.head.appendChild(style);

    emailInput.addEventListener('input', function(e) {
        clearMessages();
        const email = e.target.value.trim();
        
        // Clear previous timeout
        if (emailTimeout) {
            clearTimeout(emailTimeout);
        }

        // Clear validation if email is empty
        if (!email) {
            validationUI.className = 'wpclm-email-validation';
            validationUI.innerHTML = '';
            isEmailValid = false;
            submitButton.disabled = true;
            return;
        }

        // Basic email format validation
        const emailRegex = /^[^\s@]+@[^\s@]+\.[^\s@]+$/;
        if (!emailRegex.test(email)) {
            validationUI.className = 'wpclm-email-validation error';
            validationUI.innerHTML = wpclm_vars.invalid_format;
            isEmailValid = false;
            submitButton.disabled = true;
            return;
        }

        // Wait 1 second after user stops typing
        emailTimeout = setTimeout(function() {
            // Show loading message
            validationUI.className = 'wpclm-email-validation loading';
            validationUI.innerHTML = '<span class="wpclm-email-spinner"></span>' + 
                                   wpclm_vars.verifying_long;

            $.ajax({
                url: wpclm_vars.ajax_url,
                type: 'POST',
                data: {
                    action: 'wpclm_verify_email',
                    email: email,
                    nonce: wpclm_vars.nonce
                },
                success: function(response) {
                    clearMessages();
                    if (response.success) {
                        validationUI.className = 'wpclm-email-validation success';
                        validationUI.innerHTML = '<span class="dashicons dashicons-yes"></span> ' + 
                                               response.data.message;
                        isEmailValid = true;
                        submitButton.disabled = false;
                    } else {
                        validationUI.className = 'wpclm-email-validation error';
                        validationUI.innerHTML = '<span class="dashicons dashicons-no"></span> ' + 
                                               response.data.message;
                        isEmailValid = false;
                        submitButton.disabled = true;
                    }
                },
                error: function() {
                    clearMessages();
                    validationUI.className = 'wpclm-email-validation error';
                    validationUI.innerHTML = wpclm_vars.error_occurred;
                    isEmailValid = false;
                    submitButton.disabled = true;
                }
            });
        }, 1000);
    });
})(jQuery);