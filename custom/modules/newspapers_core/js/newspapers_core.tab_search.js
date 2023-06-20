(function ($, Drupal, DrupalSettings) {
    'use strict';

    Drupal.behaviors.multiSubmit = {
        attach: function (context, settings) {
            // On empty fulltext input error: switch to Fulltext tab + focus input.
            if ($('div[aria-label="Error message"]').length) {
                $('a#tab-fulltext').click();
                $('#fulltext input[type=text]').focus();
            }

            // Autofocus the search input of clicked tab.
            $('.nav-tabs a[data-toggle="tab"]').on('shown.bs.tab', function () {
                $('#newspapers-core-homepage input[type="text"]:visible').focus();
            });

            // Simuate click function when user presses enter key.
            $('input[type="text"]').on('keypress', function (e) {
                if (e.keyCode == 13) {
                    $('#newspapers-core-homepage input[type=submit]:visible').focus();
                    $('#newspapers-core-homepage input[type=submit]:visible').click();
                    return FALSE;
                }
            });
        },
    };
})(jQuery, Drupal, drupalSettings);
