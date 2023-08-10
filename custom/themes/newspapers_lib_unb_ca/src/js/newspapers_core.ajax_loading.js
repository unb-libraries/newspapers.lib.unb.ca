(function ($, Drupal) {
    Drupal.behaviors.facetAnimation = {
        attach: function (context) {
            $('.facets-widget-checkbox .facet-item').click(function(event) {
                if (event.originalEvent !== undefined) {
                    $('img', this).toggleClass('d-none');
                    $('input', this).click();
                    return false;
                }
            });
        },
    };
})(jQuery, Drupal);
