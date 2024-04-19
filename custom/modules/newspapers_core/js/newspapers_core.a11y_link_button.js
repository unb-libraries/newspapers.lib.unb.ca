(function ($, Drupal) {
    Drupal.behaviors.accessibleLinkButton = {
        attach: function() {
            // Simulate click when link 'button' focused and space key pressed.
            const link_button = 'a[role="button"]';
            $(link_button).on('keypress', function (event) {
                if (event.key === ' ' || event.key === 'Spacebar') {
                    event.preventDefault();
                    this.click();
                }
            });
        }
    };
})(jQuery, Drupal);
