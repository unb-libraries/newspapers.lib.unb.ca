(function($) {
    'use strict';

    Drupal.behaviors.multiSubmit = {
        attach: function (context, settings) {
            // Simulate click function when user presses enter key.
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
