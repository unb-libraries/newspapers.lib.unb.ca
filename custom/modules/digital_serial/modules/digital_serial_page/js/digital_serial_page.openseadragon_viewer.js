var viewer = OpenSeadragon({
  id: "seadragon-viewer",
  prefixUrl: "//openseadragon.github.io/openseadragon/images/",
  tileSources: {
    type: 'image',
    url:  drupalSettings.digital_serial_page.jpg_filepath
  },
  overlays: drupalSettings.digital_serial_page.overlays
});
