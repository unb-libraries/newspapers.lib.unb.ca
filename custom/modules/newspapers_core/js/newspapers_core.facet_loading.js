(function ($, Drupal, once) {
    Drupal.behaviors.facetAnimation = {
        attach: function (context) {
            once('customFacetBehavior', '.facets-widget-checkbox .facet-item', context).forEach(function(element) {
                element.click(function(event) {
                    if (event.originalEvent !== undefined) {
                        $('img', this).toggleClass('d-none');
                        $('input', this).click();
                        return false;
                    }
                });
            });
        },
    };
})(jQuery, Drupal, once);
