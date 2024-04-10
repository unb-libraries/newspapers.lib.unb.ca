(function ($, Drupal) {
    'use strict';
    Drupal.behaviors.accessibility = {
        attach: function () {
            // Make OpenSeaDragon viewer controls keyboard accessible.
            $('#seadragon-viewer .openseadragon-container div[title]').attr('tabindex', '0');
            // Remove OpenSeaDragon viewer search highlight links from keyboard tab flow.
            $('#seadragon-viewer a.digital-serial-page-highlight').attr('tabindex', '-1');
        },
    };
})(jQuery, Drupal);
