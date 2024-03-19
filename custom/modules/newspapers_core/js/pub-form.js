/**
 * @file NB Newspapers Core Publication Form Enhancements
 */

function firstIssueDateRange() {
    /**
     * Set First Issue Date interface within Date of Publication fieldset.
     */
    if (jQuery("#edit-field-first-issue-date-type").val() == 'approximate') {
        jQuery("#edit-field-first-issue-date-0 legend .fieldset__label").text("First Issue Date Range");
        jQuery("#edit-field-first-issue-date-0 .form-datetime-wrapper:first-child .form-item__label").text("Approximate start date");
        jQuery("#edit-field-first-issue-date-0 .form-datetime-wrapper:nth-child(2) .form-item__label").addClass("form-required").text("Approximate end date");
        jQuery("#edit-field-first-issue-date-0 .form-datetime-wrapper:nth-child(2)").slideDown(300);
        jQuery("#edit-field-first-issue-date-0 .fieldset__description").slideDown(300);
    } else {
        jQuery("#edit-field-first-issue-date-0 legend .fieldset__label").text("First Issue Date");
        jQuery("#edit-field-first-issue-date-0 .form-datetime-wrapper:first-child .form-item__label").text("Date");
        jQuery("#edit-field-first-issue-date-0 .form-datetime-wrapper:first-child .form-item__label").addClass("form-required");
        /**
         * Clear the end date widget when Approximate is NOT selected.
         */
        jQuery("#edit-field-first-issue-date-0-end-value-date").datepicker("setDate", null).val('');
        /**
         * Clear the start date widget when Exact is NOT selected.
         */
        if (jQuery("#edit-field-first-issue-date-type").val() != 'exact') {
            jQuery("#edit-field-first-issue-date-0-value-date").datepicker("setDate", null).val('');
        }
        jQuery("#edit-field-first-issue-date-0 .form-datetime-wrapper:nth-child(2) .form-item__label").removeClass("form-required");
        jQuery("#edit-field-first-issue-date-0 .form-datetime-wrapper:nth-child(2)").slideUp(200);
        jQuery("#edit-field-first-issue-date-0 .fieldset__description").slideUp(200);
        // jQuery("#edit-field-first-issue-date-wrapper .alert-danger").slideUp();
    }
}

function lastIssueDateRange() {
    /**
     * Set Last Issue Date interface within Date of Publication fieldset.
     */
    if (jQuery("#edit-field-last-issue-date-type").val() == 'approximate') {
        jQuery("#edit-field-last-issue-date-0 legend .fieldset__label").text("Last Issue Date Range");
        jQuery("#edit-field-last-issue-date-0 .form-datetime-wrapper:first-child .form-item__label").addClass("form-required").text("Approximate start date");
        jQuery("#edit-field-last-issue-date-0 .form-datetime-wrapper:nth-child(2) .form-item__label").addClass("form-required").text("Approximate end date");
        jQuery("#edit-field-last-issue-date-0 .form-datetime-wrapper:nth-child(2)").slideDown(300);
        jQuery("#edit-field-last-issue-date-0 .fieldset__description").slideDown(300);
    } else {
        jQuery("#edit-field-last-issue-date-0 legend .fieldset__label").text("Last Issue Date");
        jQuery("#edit-field-last-issue-date-0 .form-datetime-wrapper:first-child .form-item__label").text("Date");
        jQuery("#edit-field-last-issue-date-0 .form-datetime-wrapper:first-child .form-item__label").addClass("form-required");
        /**
         * Clear the start date widget when Exact is NOT selected.
         */
        if (jQuery("#edit-field-last-issue-date-type").val() != 'exact') {
            jQuery("#edit-field-last-issue-date-0-value-date").datepicker("setDate", null).val('');
        }
        /**
         * Clear the end date widget when Approximate in NOT selected.
         */
        jQuery("#edit-field-last-issue-date-0 .form-datetime-wrapper:nth-child(2) .form-item__label").removeClass("form-required");
        jQuery("#edit-field-last-issue-date-0 .form-datetime-wrapper:nth-child(2)").slideUp(200);
        jQuery("#edit-field-last-issue-date-0 .fieldset__description").slideUp(200);
        // jQuery("#edit-field-last-issue-date-wrapper .alert-danger").slideUp();
    }
}

function updatePrecedingLabel() {
    var $upLabel;
    var $upDescription;
    var $selection = jQuery("#edit-field-serial-relationship-op-pre option:selected").val();
    switch($selection) {
        case 'continues':
            $upLabel = 'Continues:';
            $upDescription = 'Please select a single publication from the list';
            break;

        case 'union':
            $upLabel = 'Formed By The Union Of:';
            $upDescription = 'Please select at least 2 publications from the list';
            break;

        case 'absorbed':
            $upLabel = 'Absorbed:';
            $upDescription = 'Please select a single publication from the list';
            break;

        case 'separated':
            $upLabel = 'Separated from:';
            $upDescription = 'Please select a single publication from the list';
            break;

        default:
            $upLabel = 'N/A';
    }
    jQuery('label[for="edit-field-serial-relation-pre-ref-up"]').addClass("form-required").text($upLabel);
    jQuery('.form-item-field-serial-relation-pre-ref-up .description').text($upDescription);
}

function updateSucceedingLabel() {
    var $upLabel;
    var $downLabel;
    var $upDescription;
    var $downDescription;
    var $selection = jQuery("#edit-field-serial-relationship-op-suc option:selected").val();
    switch($selection) {
        case 'continued_by':
            $downLabel = 'Continued by:';
            $downDescription = 'Please select a single publication from the list';
            break;

        case 'split_into':
            $downLabel = 'Split into:';
            $downDescription = 'Please select at least 2 publications from the list';
            break;

        case 'absorbed_by':
            $downLabel = 'Absorbed by:';
            $downDescription = 'Please select a single publication from the list';
            break;

        case 'merged_with_form':
            $upLabel = 'Merged with:';
            $downLabel = 'to form:';
            $upDescription = $downDescription = 'Please select a single publication from the list';
            break;

        default:
            $upLabel = $downLabel = $upDescription = $downDescription = 'N/A';
    }
    jQuery('label[for="edit-field-serial-relation-suc-ref-up"]').addClass("form-required").text($upLabel);
    jQuery('.form-item-field-serial-relation-suc-ref-up .description').text($upDescription);
    jQuery('label[for="edit-field-serial-relation-suc-ref-dn"]').addClass("form-required").text($downLabel);
    jQuery('.form-item-field-serial-relation-suc-ref-dn .description').text($downDescription);
}

(function ($, Drupal) {
    'use strict';

    Drupal.behaviors.inputPublicationDate = {
        attach: function () {
            /**
             * Adjust Date component + Preceding/Succeeding label text.
             */
            firstIssueDateRange();
            lastIssueDateRange();
            updatePrecedingLabel();
            updateSucceedingLabel();

            $('#edit-field-first-issue-date-type').change(firstIssueDateRange);
            $('#edit-field-last-issue-date-type').change(lastIssueDateRange);
            $('#edit-field-serial-relationship-op-pre').change(updatePrecedingLabel);
            $('#edit-field-serial-relationship-op-suc').change(updateSucceedingLabel);
        }
    };
})(jQuery, Drupal);
