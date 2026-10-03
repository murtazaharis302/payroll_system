var oldCity = window.oldCity;
var oldLocation = window.oldLocation;

$(document).ready(function() {

    if(!oldCity){
        $('select[name="location"]').prop('disabled', true);
    }

    if (oldCity) {
        $('select[name="city"]').val(oldCity);
        loadArea(oldCity, true);
    }

    // Standard city change event
    $('select[name="city"]').change(function() {
        var city_id = $(this).val();
        loadArea(city_id, false); 
    });

    function loadArea(city_id, restoringOld){
        if (city_id) {
            $.ajax({
                url: window.getLocationsUrl + '/' + city_id,
                type: 'GET',
                dataType: 'json',
                success: function(data) {

                    $('select[name="location"]').prop('disabled', false);
                    $('select[name="location"]').empty();
                    $('select[name="location"]').append('<option value="">Select Area</option>');

                    $.each(data, function(key, value) {

                        let selected = '';

                        // Only select old value the FIRST time (restoring)
                        if (restoringOld && oldLocation && value.id == oldLocation) {
                            selected = 'selected';
                        }

                        $('select[name="location"]').append(
                            '<option value="' + value.id + '" ' + selected + '>' + value.area_name + '</option>'
                        );
                    });
                },
                error: function(xhr) {
                    console.log(xhr.responseText);
                }
            });
        } else {
            $('select[name="location"]').prop('disabled', true);
            $('select[name="location"]').empty();
            $('select[name="location"]').append('<option value="" selected>Select Area</option>');
        }
    }
});
$(document).on('submit', 'form', function () {
    $('#location, #city').prop('disabled', false);
});
