/**
 * @file NB Newspapers Core Publication Form Enhancements
 */

function firstIssueDateRange() {
    /**
     * Set First Issue Date interface within Date of Publication fieldset.
     */
    if (jQuery("#edit-field-first-issue-date-is-approx-value").is(":checked")) {
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
        /**
         * Remove 'value should not be null' validation alert if 'First Issue is Approximate' is unchecked.
         */
        jQuery("#edit-field-first-issue-date-wrapper .alert-danger").slideUp();
    }
}

function lastIssueDateRange() {
    /**
     * Set Last Issue Date interface within Date of Publication fieldset.
     */
    if (jQuery("#edit-field-last-issue-date-is-approx-value").is(":checked")) {
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
        /**
         * Remove 'value should not be null' validation alert if 'First Issue is Approximate' is unchecked.
         */
        jQuery("#edit-field-last-issue-date-wrapper .alert-danger").slideUp();
    }
}

jQuery(document).ready(function () {
    /**
     * Adjust Date of Publication fieldset daterange widgets on form load + UI click re: approximate dates.
     */
    firstIssueDateRange();
    lastIssueDateRange();

    jQuery("#edit-field-first-issue-date-is-approx-value").click(firstIssueDateRange);
    jQuery("#edit-field-last-issue-date-is-approx-value").click(lastIssueDateRange);
});

