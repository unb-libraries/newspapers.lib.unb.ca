(function ($, Drupal) {
    Drupal.behaviors.facetAnimation = {
        attach: function () {
            $('.facets-widget-checkbox .facet-item').click(function(e) {
                if (e.originalEvent !== undefined) {
                    $('label img', this).toggleClass('d-none');
                    $('input', this).click();
                }
            });
        },
    };
})(jQuery, Drupal);
