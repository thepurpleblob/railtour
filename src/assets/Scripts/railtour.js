/**
 * Created by howard on 02/06/2017.
 */

define(["jquery", "validate"], function($) {

    return {
        init: function() {

            // Submit select on change
            $('.select_autosubmit').change(
                function() {
                    this.form.submit();
                }
            );

            // Initialise form validation
            $('.validate-form').validate();

        }
    };

})
