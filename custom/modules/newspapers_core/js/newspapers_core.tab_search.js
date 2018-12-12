(function($) {
    'use strict';

    Drupal.behaviors.multiSubmit = {
        attach: function (context, settings) {
            // Autofocus the search input of active tab.
            $('.search-form input:visible').focus();
            $('.tab a[data-toggle="tab"]').on('shown.bs.tab', function() {
                $('.search-form input:visible').focus();
            });

            // Simuate click function when user presses enter key.
            $("input").on("keypress", function (e) {
                if (e.keyCode == 13) {
                    $(".search-form button[type=submit]:visible").focus();
                    $(".search-form button[type=submit]:visible").click();
                    return false;
                }
            });
        },
    };
})(jQuery);
