// Safari (but not Chrome, whose user agent also names Safari) cannot draw
// huge canvases. Decided here, not on the server, as the viewer page is cached
// for every browser.
var userAgent = navigator.userAgent.toLowerCase();
var useCanvas = userAgent.indexOf('safari') === -1 || userAgent.indexOf('chrome') !== -1;

var viewer = OpenSeadragon({
    id: "seadragon-viewer",
    prefixUrl: "//openseadragon.github.io/openseadragon/images/",
    tileSources: drupalSettings.digital_serial_page.tile_sources,
    overlays: drupalSettings.digital_serial_page.overlays,
    useCanvas: useCanvas,
    toolbar: "toolbarDiv"
});
