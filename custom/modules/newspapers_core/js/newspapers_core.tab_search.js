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

            // Ensure fulltext submissions contain string else prevent submission & report validity.
            $('form').submit(function(event) {
                const search_fulltext = '#newspapers-core-homepage input[name="input_fulltext"]';
                if ($(search_fulltext).is(':visible') === true) {
                    $(search_fulltext).attr('required', true);
                    if (this.checkValidity() === false) {
                        $(search_fulltext).addClass('is-invalid').attr('aria-invalid', 'true');
                        event.preventDefault();
                        this.reportValidity();
                    }
                }
            });
        },
    };
})(jQuery, Drupal);
