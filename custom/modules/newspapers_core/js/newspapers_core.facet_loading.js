/**
 * @file
 * Show animated throbber image on facet checkbox widget > item click.
 * Also extends clickable area of facet item to improve accessibility.
 */
(function ($) {
    $('.facets-widget-checkbox .facet-item').click(function(e) {
        if (e.originalEvent !== undefined) {
            $('label img', this).toggleClass('d-none');
            $('input', this).click();
        }
    });
})(jQuery);
