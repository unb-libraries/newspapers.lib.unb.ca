(function ($, Drupal) {
    Drupal.behaviors.searchInputRequired = {
        attach: function() {
            // Ensure required attribute present input of type 'search'.
            const search_input = 'input[type="search"]';
            const required = $(search_input).attr('required');
            if (typeof required === 'undefined' && required !== true) {
                $(search_input).attr('required', true);
            }

            // If form validity check fails then prevent submission & report.
            $('form').submit(function(e) {
                console.log(this.validity);
                if (this.checkValidity() === false) {
                    e.preventDefault();
                    this.reportValidity();
                }
            });
        }
    }
})(jQuery, Drupal);
