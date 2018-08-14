(function($) {
    'use strict';

    Drupal.behaviors.inputDateWidget = {
        attach: function (context, settings) {
            // Workaround: change Exposed filters type from "text' to 'date' for Views date fields.
            $("input[name*='issue_date']").prop("type", "date");
        },
    };
})(jQuery);
