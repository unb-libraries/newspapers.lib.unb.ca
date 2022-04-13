(function ($, Drupal) {
    // Fix core's required handling (https://www.drupal.org/project/drupal/issues/2855139).
    $(document).bind('state:required', function (e) {
        if (e.trigger) {
            var fields = $(e.target).find('input, select, textarea, fieldset');
            fields.each(function() {
                var label = 'label' + (this.id ? '[for=' + this.id + ']' : '');
                var $field_labels = $(e.target).find(label);
                var $fieldset_legends = $(e.target).find('legend span.fieldset-legend');
                var $labels = $field_labels.add($fieldset_legends);
                if (e.value) {
                    $(this).attr({ required: 'required', 'aria-required': 'aria-required' });
                    $labels.each(function() {
                        $(this).addClass('js-form-required form-required');
                    });
                } else {
                    $(this).removeAttr('required aria-required');
                    $labels.each(function() {
                        $(this).removeClass('js-form-required form-required');
                    });
                }
            })
        }
    });

    // Add jQuery sliding effect for 'visible-slide' state.
    $(document).bind('state:visible-slide', function(e) {
        if (e.trigger) {
            var effect = e.value ? 'slideDown' : 'slideUp';
            var duration = 300;
            $(e.target).closest('.js-form-item, .js-form-submit, .js-form-wrapper')[effect ](duration);
        }
    });
})(jQuery, Drupal);
