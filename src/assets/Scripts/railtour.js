/**
 * Created by howard on 02/06/2017.
 */

define(["jquery", "validate", "tinymce"], function($, validate, tinymce) {

    alert('into define');

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

            // TinyMCE
            tinymce.init({
                selector: 'textarea'
            });

            alert('Got here too');
        }
    };

})
