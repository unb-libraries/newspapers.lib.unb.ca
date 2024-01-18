/**
 * @file NB Newspapers Core Citation.
 *
 * Depends on:
 * - Bootstrap popovers (https://getbootstrap.com/docs/4.6/components/popovers)
 * - Drupal clipboard.js (https://www.drupal.org/project/clipboardjs)
 */
(function ($, Drupal) {
    'use strict';

    Drupal.behaviors.copy_citation = {
        attach: function (context, settings) {
            let copyButton= '#clipboardjs-button';
            let citationModal = '#citation-modal';
            function triggerPopover(popoverContent) {
                let options = {
                    content: popoverContent,
                    delay: { 'show': 500 },
                    html: true,
                    placement: 'left',
                    trigger: 'manual',
                };
                $(copyButton).popover(options).popover('show');
                $(copyButton).blur(function () {
                    $(this).popover('hide');
                    // Destroy popover object so cycled keyboard focuses don't re-trigger.
                    $(this).popover('dispose');
                });
            }

            // https://clipboardjs.com/#events. See also Advanced Usage > Bootstrap Modals.
            Drupal.clipboard = new ClipboardJS(copyButton, {
                container: $(citationModal).get(0)
            });
            Drupal.clipboard.on('success', function (e) {
                triggerPopover('<span class="fa-solid fa-clipboard-check fa-lg mr-1 text-success"></span>Citation copied');
                // Need to clear selection & move focus back to button that triggered the copy action.
                e.clearSelection();
            });

            Drupal.clipboard.on('error', function (e) {
                triggerPopover('<span class="fa-solid fa-circle-exclamation mr-1 text-danger"></span>Unable to copy citation');
            });
        },
    };
})(jQuery, Drupal);
