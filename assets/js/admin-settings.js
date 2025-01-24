jQuery(document).ready(function($) {
    'use strict';

    // Login URL validation
    $('#wpclm_login_url').on('input', function() {
        let value = $(this).val();
        
        // Ensure starts with /
        if (!value.startsWith('/')) {
            value = '/' + value;
        }
        
        // Ensure ends with /
        if (!value.endsWith('/')) {
            value = value + '/';
        }
        
        // Update field value
        $(this).val(value);
    });

    // Media Uploader for Logo
    var logoUploader;
    $('#wpclm_upload_logo_button').on('click', function(e) {
        e.preventDefault();

        if (logoUploader) {
            logoUploader.open();
            return;
        }

        logoUploader = wp.media({
            title: wpclm_admin.logo_upload_title,
            button: {
                text: wpclm_admin.logo_upload_button
            },
            multiple: false
        });

        logoUploader.on('select', function() {
            var attachment = logoUploader.state().get('selection').first().toJSON();
            $('#wpclm_logo').val(attachment.url);
            $('.wpclm-logo-preview').html('<img src="' + attachment.url + '" style="max-width: ' + $('#wpclm_logo_width').val() + 'px">');
            $('#wpclm_remove_logo_button').show();
        });

        logoUploader.open();
    });

    // Remove Logo
    $('#wpclm_remove_logo_button').on('click', function(e) {
        e.preventDefault();
        $('#wpclm_logo').val('');
        $('.wpclm-logo-preview').empty();
        $(this).hide();
    });

    // Logo Width Change
    $('#wpclm_logo_width').on('change', function() {
        var logoUrl = $('#wpclm_logo').val();
        if (logoUrl) {
            $('.wpclm-logo-preview img').css('max-width', $(this).val() + 'px');
        }
    });

    // Media Uploader for Background Image
    var bgUploader;
    $('#wpclm_upload_background_button').on('click', function(e) {
        e.preventDefault();

        if (bgUploader) {
            bgUploader.open();
            return;
        }

        bgUploader = wp.media({
            title: wpclm_admin.bg_upload_title,
            button: {
                text: wpclm_admin.bg_upload_button
            },
            multiple: false
        });

        bgUploader.on('select', function() {
            var attachment = bgUploader.state().get('selection').first().toJSON();
            $('#wpclm_background_image').val(attachment.url);
            $('.wpclm-background-preview').html('<img src="' + attachment.url + '" style="max-width: 300px">');
            $('#wpclm_remove_background_button').show();
        });

        bgUploader.open();
    });

    // Remove Background Image
    $('#wpclm_remove_background_button').on('click', function(e) {
        e.preventDefault();
        $('#wpclm_background_image').val('');
        $('.wpclm-background-preview').empty();
        $(this).hide();
    });

    // Initialize Color Pickers
    if ($.fn.wpColorPicker) {
        $('.wpclm-color-picker').wpColorPicker();
    }

    // Initialize tooltips
    $('.wpclm-help-tip').tooltip({
        position: {
            my: 'left center',
            at: 'right+10 center'
        }
    });

    // Debug Features
    const DebugManager = {
        init: function() {
            this.bindEvents();
            if ($('#wpclm_enable_debugging').is(':checked')) {
                this.initRealTimeUpdates();
            }
        },

        bindEvents: function() {
            $('#wpclm-view-log').on('click', this.viewLog);
            $('#wpclm-clear-log').on('click', this.clearLog);
            $('#wpclm_enable_debugging').on('change', this.toggleDebugMode);
            $('.wpclm-debug-info-toggle').on('click', this.toggleDebugInfo);
        },

        viewLog: function(e) {
            e.preventDefault();
            
            $.ajax({
                url: wpclm_admin.ajaxurl,
                type: 'POST',
                data: {
                    action: 'wpclm_view_debug_log',
                    nonce: wpclm_admin.nonce
                },
                beforeSend: function() {
                    $(e.target).prop('disabled', true).text(wpclm_admin.loading_text);
                },
                success: function(response) {
                    if (response.success) {
                        DebugManager.showLogViewer(response.data.log_contents);
                    } else {
                        alert(response.data.message);
                    }
                },
                error: function() {
                    alert(wpclm_admin.error_message);
                },
                complete: function() {
                    $(e.target).prop('disabled', false).text(wpclm_admin.view_log_text);
                }
            });
        },

        clearLog: function(e) {
            e.preventDefault();
            
            if (!confirm(wpclm_admin.confirm_clear_log)) {
                return;
            }

            $.ajax({
                url: wpclm_admin.ajaxurl,
                type: 'POST',
                data: {
                    action: 'wpclm_clear_debug_log',
                    nonce: wpclm_admin.nonce
                },
                beforeSend: function() {
                    $(e.target).prop('disabled', true).text(wpclm_admin.clearing_text);
                },
                success: function(response) {
                    if (response.success) {
                        if ($('#wpclm-log-viewer').length) {
                            $('#wpclm-log-viewer .log-contents').empty();
                        }
                        alert(wpclm_admin.log_cleared_message);
                    } else {
                        alert(response.data.message);
                    }
                },
                error: function() {
                    alert(wpclm_admin.error_message);
                },
                complete: function() {
                    $(e.target).prop('disabled', false).text(wpclm_admin.clear_log_text);
                }
            });
        },

        showLogViewer: function(contents) {
            const viewer = $('<div>', {
                id: 'wpclm-log-viewer',
                class: 'wpclm-log-viewer'
            }).append(
            $('<div>', {
                class: 'log-contents',
                html: '<pre>' + contents + '</pre>'
            }),
            $('<div>', {
                class: 'log-controls'
            }).append(
            $('<button>', {
                class: 'button refresh-log',
                text: wpclm_admin.refresh_log_text
            }),
            $('<button>', {
                class: 'button download-log',
                text: wpclm_admin.download_log_text
            })
            )
            );

            viewer.dialog({
                title: wpclm_admin.log_viewer_title,
                width: Math.min($(window).width() * 0.8, 800),
                height: Math.min($(window).height() * 0.8, 600),
                modal: true,
                dialogClass: 'wp-dialog',
                close: function() {
                    $(this).dialog('destroy').remove();
                }
            });

            // Bind log viewer events
            viewer.find('.refresh-log').on('click', this.refreshLog);
            viewer.find('.download-log').on('click', this.downloadLog);
        },

        refreshLog: function(e) {
            e.preventDefault();
            
            $.ajax({
                url: wpclm_admin.ajaxurl,
                type: 'POST',
                data: {
                    action: 'wpclm_view_debug_log',
                    nonce: wpclm_admin.nonce
                },
                beforeSend: function() {
                    $(e.target).prop('disabled', true);
                },
                success: function(response) {
                    if (response.success) {
                        $('#wpclm-log-viewer .log-contents pre').html(response.data.log_contents);
                    }
                },
                complete: function() {
                    $(e.target).prop('disabled', false);
                }
            });
        },

        downloadLog: function(e) {
            e.preventDefault();
            
            const contents = $('#wpclm-log-viewer .log-contents pre').text();
            const blob = new Blob([contents], { type: 'text/plain' });
            const url = window.URL.createObjectURL(blob);
            const a = document.createElement('a');
            
            a.style.display = 'none';
            a.href = url;
            a.download = 'wpclm-debug.log';
            
            document.body.appendChild(a);
            a.click();
            
            window.URL.revokeObjectURL(url);
            document.body.removeChild(a);
        },

        initRealTimeUpdates: function() {
            // Check for new log entries every 30 seconds
            this.updateInterval = setInterval(function() {
                if ($('#wpclm-log-viewer').length) {
                    DebugManager.refreshLog({ preventDefault: function(){} });
                }
            }, 30000);
        },

        toggleDebugMode: function() {
            const isEnabled = $(this).is(':checked');
            $('.wpclm-debug-actions').toggle(isEnabled);
            
            if (isEnabled) {
                DebugManager.initRealTimeUpdates();
            } else {
                clearInterval(DebugManager.updateInterval);
            }
        },

        toggleDebugInfo: function(e) {
            e.preventDefault();
            $('.wpclm-debug-info').slideToggle();
        }
    };

    // Initialize Debug Manager
    DebugManager.init();

});