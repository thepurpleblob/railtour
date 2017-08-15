/**
 * Created by howard on 01/06/2017.
 */

require.config({
    paths : {
        'jquery' : 'Utils/jquery',
        'validate' : 'Utils/validate',
        'tinymce' : 'Utils/tinymce.min'
    },
    shim: {
        'validate' :  ['jquery'],
        'tinymce' : {exports: 'tinymce'}
    }
});

require(["jquery", "validate", "tinymce"], function($, validate, tinymce) {

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

    // CRS loader
    $("#crsText").change(
        function() {
            var crs = $(this).val();
            console.log("Got the new CRS " + crs);
            var name = $("#nameText").val();
            var path = $("#ajaxpathHidden").val();
            console.log("Got the path " + path);
            //if (!name) {
            if (true) {
                $.ajax({
                    url: path,
                    data: { 'crstyped': crs },
                    type: 'post',
                    success: function(output) {
                        $("#nameText").val(output);
                    }
                });
                //$("#srps_bookingbundle_destinationtype_name").val(crs);
            }
        }
    );
})