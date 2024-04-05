var viewer = OpenSeadragon({
  id: "seadragon-viewer",
  prefixUrl: "//openseadragon.github.io/openseadragon/images/",
  tileSources: drupalSettings.digital_serial_page.tile_sources,
  overlays: drupalSettings.digital_serial_page.overlays,
  useCanvas: drupalSettings.digital_serial_page.use_canvas
});

(function ($, Drupal) {
    'use strict';
    Drupal.behaviors.accessibility = {
        attach: function (context, settings) {
            // Make OpenSeaDragon viewer controls keyboard accessible.
            $('#seadragon-viewer .openseadragon-container div[title]').attr('tabindex', '0');
            // Remove OpenSeaDragon viewer search highlight links from keyboard tab flow.
            $('#seadragon-viewer .openseadragon-container a.digital-serial-page-highlight').attr('tabindex', '-1');
        },
    };
})(jQuery, Drupal);
