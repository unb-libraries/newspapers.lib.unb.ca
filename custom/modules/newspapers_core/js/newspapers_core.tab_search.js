(function ($, Drupal) {
    'use strict';

    Drupal.behaviors.multiSubmit = {
        attach: function () {
            // On empty fulltext input error: switch to Fulltext tab + focus input.
            if ($('div[aria-label="Error message"]').length) {
                $('a#tab-fulltext').click();
                $('#newspapers-core-homepage input[type="search"]:visible').focus();
            }

            // Autofocus the search input of clicked tab.
            $('.nav-tabs a[data-toggle="tab"]').on("shown.bs.tab", function () {
                $('#newspapers-core-homepage input[type="search"]:visible').focus();
            });

            // Simulate click function when user presses enter key.
            $('input[type="search"]').on("keypress", function (e) {
                if (e.keyCode == 13) {
                    $('#newspapers-core-homepage input[type=submit]:visible').focus();
                    $('#newspapers-core-homepage input[type=submit]:visible').click();
                    return false;
                }
            });
        },
    };
})(jQuery, Drupal);
