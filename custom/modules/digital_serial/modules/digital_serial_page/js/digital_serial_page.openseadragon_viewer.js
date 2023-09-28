var viewer = OpenSeadragon({
  id: "seadragon-viewer",
  prefixUrl: "//openseadragon.github.io/openseadragon/images/",
  tileSources: drupalSettings.digital_serial_page.tile_sources,
  overlays: drupalSettings.digital_serial_page.overlays
});

(function ($, Drupal) {
    'use strict';
    Drupal.behaviors.multiSubmit = {
        attach: function (context, settings) {
            // Accessible OpenSeaDragon viewer controls.
            $('#seadragon-viewer .openseadragon-container div[title]').attr('tabindex', '0');
        },
    };
})(jQuery, Drupal);
