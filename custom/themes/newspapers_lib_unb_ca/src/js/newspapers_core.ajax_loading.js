(function($) {
    'use strict';

    Drupal.behaviors.ajaxLoader = {
        attach: function (context, settings) {
            // Ajax throbber with Views Infinite Scrolling fix.
            $('.pager__item a.button').one('click', function () {
                $('span', this).toggleClass('hide');
            });

            // Ajax throbber for facet items.
            $('.facets-widget-checkbox .facet-item').one('click', function () {
                $('img', this).toggleClass('hide');
            });
        },
    };
})(jQuery);
