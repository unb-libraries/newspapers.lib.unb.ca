(function ($, Drupal) {
    'use strict';
    Drupal.behaviors.accessibility = {
        attach: function () {
            // Make OpenSeaDragon viewer controls keyboard accessible.
            $('#seadragon-viewer .openseadragon-container div[title]').attr('tabindex', '0');
            setTimeout(function() {
                // Remove OpenSeaDragon viewer empty highlight links from keyboard tab flow.
                // Not ideal but allow 1 second for overlay to render.
                $('#seadragon-viewer a.digital-serial-page-highlight').attr('tabindex', '-1');
            }, 1000);
        },
    };
})(jQuery, Drupal);
