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
            crs = $(this).val();
            console.log("Got the new CRS " + crs);
            name = $("#nameText").val();
            console.log("Got the name " + name);
            //if (!name) {
            if (true) {
                $.ajax({

                    // TODO: Need to find a way to feed this ajax path in!!!
                    url: rtconfig.www + "/index.php/destination/ajax",
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