$( function() {

    // Submit select on change
    $('.select_autosubmit').change(
        function () {
            this.form.submit();
        }
    );

    // Initialise form validation
    $('.validate-form').validate();

    // TinyMCE
    tinymce.init({
        selector: 'textarea'
    });

    // Tooltips
    $('[data-toggle="tooltip"]').tooltip();

    // CRS loader
    $("#crsText").change(
        function () {
            var crs = $(this).val();
            console.log("Got the new CRS " + crs);
            var name = $("#nameText").val();
            var path = $("#ajaxpathHidden").val();
            console.log("Got the path " + path);
            //if (!name) {
            if (true) {
                $.ajax({
                    url: path,
                    data: {'crstyped': crs},
                    type: 'post',
                    success: function (output) {
                        $("#nameText").val(output);
                    }
                });
                //$("#srps_bookingbundle_destinationtype_name").val(crs);
            }
        }
    );

});