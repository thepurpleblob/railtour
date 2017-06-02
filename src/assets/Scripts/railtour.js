/**
 * Created by howard on 02/06/2017.
 */

define(["jquery"], function($) {

    return {
        init: function() {

            // Submit select on change
            $('.select_autosubmit').change(
                function() {
                    this.form.submit();
                }
            );

        }
    };

})
