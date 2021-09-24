(function ($, Drupal, DrupalSettings) {
    'use strict';

    Drupal.behaviors.ajaxLoader = {
        attach: function (context, settings) {
            // Ajax throbber with Views Infinite Scrolling fix.
            $('.pager__item a.button').once().click(function() {
                $('span', this).toggleClass('d-none');
            });

            $('.facets-widget-checkbox .facet-item').once().click(function(e) {
                if (e.originalEvent !== undefined) {
                    $('img', this).toggleClass('d-none');
                    $('input', this).click();
                }
            });
        },
    };
})(jQuery, Drupal, drupalSettings);
