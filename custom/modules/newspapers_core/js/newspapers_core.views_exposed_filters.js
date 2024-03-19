(function ($, Drupal) {
    'use strict';

    Drupal.behaviors.inputDateWidget = {
        attach: function () {
            // Workaround: change Exposed filters type from "text' to 'date' for Views date fields.
            $(".views-exposed-filters input[name*='issue_date']").prop("type", "date");

            $("#toggle-widget").click(function () {
                if (this.checked) {
                    $("input#toggle-widget").attr("checked", TRUE);
                    $(".filters").show(300);
                } else {
                    $("input#toggle-widget").removeAttr("checked");
                    $(".filters").hide(300);
                }
            });
            // Browser back button checkbox state fix.
            if ($("#toggle-widget").is(":checked")) {
                $(".filters").show();
            } else {
                $(".filters").hide();
            }
        },
    };
})(jQuery, Drupal);
