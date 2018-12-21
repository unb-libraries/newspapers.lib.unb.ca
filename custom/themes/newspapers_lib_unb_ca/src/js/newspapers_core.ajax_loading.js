(function($) {
    'use strict';

    Drupal.behaviors.ajaxLoader = {
        attach: function (context, settings) {
            // Ajax throbber with Views Infinite Scrolling fix.
            $('.pager__item a.button').off('click').on('click', function () {
                $('span', this).toggleClass('hide');
            });

            $('.facets-widget-checkbox .facet-item').off('click').on('click', function(e) {
                if (e.originalEvent !== undefined) {
                    $('img', this).toggleClass('hide');
                }
            });
        },
    };
})(jQuery);
