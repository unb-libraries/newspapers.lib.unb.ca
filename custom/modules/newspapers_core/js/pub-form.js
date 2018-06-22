/**
 * @file NB Newspapers Core Publication Form Enhancements
 */

function firstIssueDateRange() {
    /**
     * Set First Issue Date interface within Date of Publication fieldset.
     */
    if (jQuery("#edit-field-first-issue-date-type").val() == 'approximate') {
        jQuery("#edit-field-first-issue-date-0 .panel-title").text("First Issue Date Range");
        jQuery("#edit-field-first-issue-date-0 label:first-child").text("Approximate start date");
        jQuery("#edit-field-first-issue-date-0-value + label").text("Approximate end date").slideDown(300);
        jQuery("#edit-field-first-issue-date-0-end-value-date").slideDown(300);
    } else {
        jQuery("#edit-field-first-issue-date-0 .panel-title").text("First Issue Date");
        jQuery("#edit-field-first-issue-date-0 label:first-child").text("Date");
        /**
         * Clear the end date widget when Approximate checkbox is unchecked.
         */
        jQuery("#edit-field-first-issue-date-0-end-value-date").datepicker("setDate", null).val('');
        jQuery("#edit-field-first-issue-date-0-value + label").slideUp(200);
        jQuery("#edit-field-first-issue-date-0-end-value-date").slideUp(200);
        // jQuery("#edit-field-first-issue-date-wrapper .alert-danger").slideUp();
    }
}

function lastIssueDateRange() {
    /**
     * Set Last Issue Date interface within Date of Publication fieldset.
     */
    if (jQuery("#edit-field-last-issue-date-type").val() == 'approximate') {
        jQuery("#edit-field-last-issue-date-0 .panel-title").text("Last Issue Date Range");
        jQuery("#edit-field-last-issue-date-0 label:first-child").text("Approximate start date");
        jQuery("#edit-field-last-issue-date-0-value + label").text("Approximate end date").slideDown(300);
        jQuery("#edit-field-last-issue-date-0-end-value-date").slideDown(300);
    } else {
        jQuery("#edit-field-last-issue-date-0 .panel-title").text("Last Issue Date");
        jQuery("#edit-field-last-issue-date-0 label:first-child").text("Date");
        /**
         * Clear the end date widget when Approximate checkbox is unchecked.
         */
        jQuery('#edit-field-last-issue-date-0-end-value-date').val('');
        jQuery("#edit-field-last-issue-date-0-value + label").slideUp(200);
        jQuery("#edit-field-last-issue-date-0-end-value-date").slideUp(200);
        // jQuery("#edit-field-last-issue-date-wrapper .alert-danger").slideUp();
    }
}

function updatePrecedingLabel() {
    var $upLabel;
    var $selection = jQuery("#edit-field-serial-relationship-op-pre option:selected").val();
    switch($selection) {
        case 'continues':
            $upLabel = 'Continues:';
            break;
        case 'union':
            $upLabel = 'Formed By The Union Of:';
            break;
        case 'absorbed':
            $upLabel = 'Absorbed:';
            break;
        case 'separated':
            $upLabel = 'Separated from:';
            break;
        default:
            $upLabel = 'N/A';
    }
    jQuery('label[for="edit-field-serial-relation-pre-ref-up"]').text($upLabel);
}

function updateSucceedingLabel() {
    var $upLabel;
    var $downLabel;
    var $selection = jQuery("#edit-field-serial-relationship-op-suc option:selected").val();
    switch($selection) {
        case 'continued_by':
            $downLabel = 'Continued by:';
            break;
        case 'split_into':
            $downLabel = 'Split into:';
            break;
        case 'absorbed_by':
            $downLabel = 'Absorbed by:';
            break;
        case 'merged_with_form':
            $upLabel = 'Merged with:';
            $downLabel = 'to form:';
            break;
        default:
            $upLabel = 'N/A';
            $downLabel = 'N/A';
    }
    jQuery('label[for="edit-field-serial-relation-suc-ref-up"]').text($upLabel);
    jQuery('label[for="edit-field-serial-relation-suc-ref-dn"]').text($downLabel);
}

jQuery(document).ready(function () {
    /**
     * Adjust Precedings / Succeedings select label text.
     */
    firstIssueDateRange();
    lastIssueDateRange();
    updatePrecedingLabel();
    updateSucceedingLabel();

    jQuery('#edit-field-first-issue-date-type').change(firstIssueDateRange);
    jQuery('#edit-field-last-issue-date-type').change(lastIssueDateRange);
    jQuery('#edit-field-serial-relationship-op-pre').change(updatePrecedingLabel);
    jQuery('#edit-field-serial-relationship-op-suc').change(updateSucceedingLabel);
});

