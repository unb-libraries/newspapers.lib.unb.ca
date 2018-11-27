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
        jQuery(".field--name-field-first-issue-approx-date label").addClass("form-required");
    } else {
        jQuery("#edit-field-first-issue-date-0 .panel-title").text("First Issue Date");
        jQuery("#edit-field-first-issue-date-0 label:first-child").text("Date");
        jQuery("#edit-field-first-issue-date-0 label").addClass("form-required");
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
        jQuery("#edit-field-first-issue-date-0-value + label").slideUp(200);
        jQuery("#edit-field-first-issue-date-0-end-value-date").slideUp(200);
        jQuery(".field--name-field-first-issue-approx-date label").removeClass("form-required");
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
        jQuery(".field--name-field-last-issue-approx-date label").addClass("form-required");
    } else {
        jQuery("#edit-field-last-issue-date-0 .panel-title").text("Last Issue Date");
        jQuery("#edit-field-last-issue-date-0 label:first-child").text("Date");
        jQuery("#edit-field-last-issue-date-0 label").addClass("form-required");
        /**
         * Clear the start date widget when Exact is NOT selected.
         */
        if (jQuery("#edit-field-last-issue-date-type").val() != 'exact') {
            jQuery("#edit-field-last-issue-date-0-value-date").datepicker("setDate", null).val('');
        }
        /**
         * Clear the end date widget when Approximate in NOT selected.
         */
        jQuery('#edit-field-last-issue-date-0-end-value-date').datepicker("setDate", null).val('');
        jQuery("#edit-field-last-issue-date-0-value + label").slideUp(200);
        jQuery("#edit-field-last-issue-date-0-end-value-date").slideUp(200);
        jQuery(".field--name-field-last-issue-approx-date label").removeClass("form-required");
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
    jQuery('label[for="edit-field-serial-relation-pre-ref-up"]').text($upLabel);
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
    jQuery('label[for="edit-field-serial-relation-suc-ref-up"]').text($upLabel);
    jQuery('.form-item-field-serial-relation-suc-ref-up .description').text($upDescription);
    jQuery('label[for="edit-field-serial-relation-suc-ref-dn"]').text($downLabel);
    jQuery('.form-item-field-serial-relation-suc-ref-dn .description').text($downDescription);
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

