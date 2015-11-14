/*
 * To change this template, choose Tools | Templates
 * and open the template in the editor.
 */
$("#srps_bookingbundle_destinationtype_crs").change(
    function() {
        crs = $(this).val();
        name = $("#srps_bookingbundle_destinationtype_name").val();
        //if (!name) {
        if (true) {
            $.ajax({
                url: "{{ path('admin_destination_ajax') }}",
                data: { 'crstyped': crs },
                type: 'post',
                success: function(output) {
                        $("#srps_bookingbundle_destinationtype_name").val(output);
                    }
            });
            //$("#srps_bookingbundle_destinationtype_name").val(crs);
        }
    }
);


