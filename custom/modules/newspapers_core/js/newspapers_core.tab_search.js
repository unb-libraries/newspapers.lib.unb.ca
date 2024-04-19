(function ($, Drupal) {
    'use strict';

    Drupal.behaviors.multiSubmit = {
        attach: function () {
            // Autofocus the search input of clicked tab.
            $('.nav-tabs a[data-toggle="tab"]').on("shown.bs.tab", function () {
                $('#newspapers-core-homepage input[type="search"]:visible').focus();
            });

            // Simulate click function when user presses enter key.
            $('input[type="search"]').on("keypress", function (event) {
                if (event.key === 'Enter') {
                    $('#newspapers-core-homepage input[type=submit]:visible').focus();
                    $('#newspapers-core-homepage input[type=submit]:visible').click();
                    return false;
                }
            });
        },
    };
})(jQuery, Drupal);
