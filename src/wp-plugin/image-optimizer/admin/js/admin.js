
// Image Optimizer Admin JavaScript
jQuery(document).ready(function($) {
    // Initialize optimization buttons in media library
    function initOptimizeButtons() {
        $(".optimize-image").off("click").on("click", function() {
            var $button = $(this);
            var attachmentId = $button.data("id");
            var $status = $button.siblings(".optimization-status");
            
            if (!$status.length) {
                $status = $("<span class=\"optimization-status\"></span>").insertAfter($button);
            }
            
            $button.prop("disabled", true);
            $status.text(imageOptimizerVars.optimizing);
            
            $.ajax({
                url: imageOptimizerVars.ajaxUrl,
                type: "POST",
                data: {
                    action: "optimize_image",
                    id: attachmentId,
                    nonce: imageOptimizerVars.nonce
                },
                success: function(response) {
                    if (response.success) {
                        $status.text(imageOptimizerVars.optimized + " " + response.data.savings + "% saved");
                        $button.text("Re-optimize");
                        
                        // Refresh the media item if we're in the grid view
                        if (wp.media && wp.media.frame && wp.media.frame.content && wp.media.frame.content.get) {
                            var selection = wp.media.frame.state().get('selection');
                            if (selection) {
                                wp.media.frame.content.get().collection.props.set({force: + new Date()});
                            }
                        }
                    } else {
                        $status.text(imageOptimizerVars.failed + ": " + response.data);
                    }
                },
                error: function() {
                    $status.text(imageOptimizerVars.failed);
                },
                complete: function() {
                    $button.prop("disabled", false);
                }
            });
        });
    }
    
    // Initialize on page load
    initOptimizeButtons();
    
    // Initialize when media modal is opened
    $(document).on("DOMNodeInserted", function(e) {
        if ($(e.target).find(".optimize-image").length > 0) {
            initOptimizeButtons();
        }
    });
    
    // Dashboard server status check
    if ($("#check-server").length) {
        $("#check-server").on("click", function() {
            var $button = $(this);
            var $status = $("#server-status");
            
            $button.prop("disabled", true);
            $status.html("<p>Checking server status...</p>");
            
            $.ajax({
                url: ajaxurl,
                type: "POST",
                data: {
                    action: "test_connection",
                    nonce: imageOptimizerVars.nonce
                },
                success: function(response) {
                    if (response.success) {
                        $status.html('<p class="server-status-ok"><span class="dashicons dashicons-yes"></span> Server is online and responding.</p>');
                    } else {
                        $status.html('<p class="server-status-error"><span class="dashicons dashicons-no"></span> Error connecting to server: ' + response.data + '</p>');
                    }
                },
                error: function() {
                    $status.html('<p class="server-status-error"><span class="dashicons dashicons-no"></span> Connection test failed. Please check your server settings.</p>');
                },
                complete: function() {
                    $button.prop("disabled", false);
                }
            });
        });
    }
    
    // Image quality slider update
    $('input[type=range][name="image_optimizer_settings[image_quality]"]').on('input', function() {
        $(this).next('output').val($(this).val());
    });
});
