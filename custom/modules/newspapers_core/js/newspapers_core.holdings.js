/**
 * @file NB Newspapers Core Publication Holdings Soft Limit Buttons.
 */
(function ($, Drupal, DrupalSettings) {
    'use strict';

    Drupal.behaviors.group_holdings = {
        attach: function (context, settings) {
             $(document, context)
                 .once('group_holdings')
                 .each( function() {
                    showToggleInit(jQuery);
                 });
        },
    };
})(jQuery, Drupal, drupalSettings);

function showToggleInit($) {
    /**
     * Configure holding soft limit toggle button labels.
     */
    const holdings = [
        "microform",
        "print"
    ];
    let showMoreLabel = 'Show All';
    let showLessLabel = 'Show Less';

    holdings.forEach( function (item, index) {
        let target = '.holding-group-' + item + ' button[data-toggle="collapse"]';
        $(target)
            .click(function () {
                let showLabel = this.innerText;
                if (showLabel == showMoreLabel) {
                    this.innerHTML = showLessLabel;
                } else {
                    this.innerHTML = showMoreLabel;
                }
            });
    });
}
