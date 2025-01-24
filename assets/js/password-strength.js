jQuery(document).ready(function($) {
    'use strict';

    function checkPasswordStrength($pass1, $pass2, $strengthResult) {
        var pass1 = $pass1.val();
        var pass2 = $pass2.val();

        // Reset the form & meter
        $strengthResult.removeClass('very-weak weak medium strong');

        // Get the password strength
        var strength = wp.passwordStrength.meter(pass1, wp.passwordStrength.userInputDisallowedList(), pass2);

        // Add the strength meter results
        switch (strength) {
            case 2:
                $strengthResult.addClass('weak');
                break;
            case 3:
                $strengthResult.addClass('medium');
                break;
            case 4:
                $strengthResult.addClass('strong');
                break;
            default:
                $strengthResult.addClass('very-weak');
        }

        return strength;
    }

    // Check password strength on input
    function initPasswordStrength() {
        var $pass1 = $('#pass1');
        var $pass2 = $('#pass2');
        var $strengthResult = $('.password-strength-meter');

        if ($pass1.length) {
            $pass1.on('keyup', function() {
                checkPasswordStrength($pass1, $pass2, $strengthResult);
            });

            $pass2.on('keyup', function() {
                checkPasswordStrength($pass1, $pass2, $strengthResult);
            });
        }
    }

    // Initialize on document ready
    initPasswordStrength();
});
