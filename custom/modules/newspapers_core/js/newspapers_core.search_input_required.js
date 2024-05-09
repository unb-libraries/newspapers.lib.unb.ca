/**
 * @file
 * Add required attribute on input elements of type 'search'.
 * Use built-in HTML5 form validation reporting and apply BS4 failure style on failure.
 */
(function ($) {
    // Ensure required attribute present input of type 'search'.
    const search_input = 'input[type="search"]';
    const required = $(search_input).attr('required');
    if (typeof required === 'undefined' && required !== true) {
        $(search_input).attr('required', true);
    }

    // If form validity check fails then prevent submission & report.
    $('form').submit(function(event) {
         if (this.checkValidity() === false) {
            $(search_input).addClass('is-invalid').attr('aria-invalid', 'true');
            event.preventDefault();
            this.reportValidity();
        }
    });
})(jQuery);
