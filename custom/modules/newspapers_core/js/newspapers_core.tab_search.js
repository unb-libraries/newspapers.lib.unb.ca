(function($) {
    'use strict';

    Drupal.behaviors.multiSubmit = {
        attach: function (context, settings) {
            // Simulate click function when user presses enter key.
            $(window).keypress(function (e) {
                var keyCode = e.which;
                if ($(".search-form input:focus") && keyCode == 13) {
                    $(".search-form button[type=submit]:visible").click();
                }
            });
        },
    };
})(jQuery);
