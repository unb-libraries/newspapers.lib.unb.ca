var viewer = OpenSeadragon({
  id: "seadragon-viewer",
  prefixUrl: "//openseadragon.github.io/openseadragon/images/",
  tileSources: drupalSettings.digital_serial_page.tile_sources,
  overlays: drupalSettings.digital_serial_page.overlays,
  useCanvas: drupalSettings.digital_serial_page.use_canvas
});
