(function($) {
    'use strict';

    Drupal.behaviors.ajaxLoader = {
        attach: function (context, settings) {
            // Ajax throbber with Views Infinite Scrolling fix.
            $('.pager__item a.button').click(function () {
                $('span', this).removeClass('hidden').addClass('show');
            });
        },
    };
})(jQuery);
