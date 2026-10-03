$(document).ready(function () {
    // Set CSRF token for all AJAX requests
$.ajaxSetup({
    headers: {
        'X-CSRF-TOKEN': $('meta[name="csrf-token"]').attr('content')
    }
});

// Handle click on Book Now button
$(document).on('click', '#car-booking-btn', function(e) {
    e.preventDefault();
    var $button = $(this);
    var carId = $button.data('carid');
    var originalBtnText = $button.html();
    console.log(carId)
    // Show loader on button
    $button.prop('disabled', false).text('Processing...');
 
    $.ajax({
        url: window.baseUrl+"/addToCart", // Make sure this route returns correct URL in the rendered page
        method: 'POST',
        data: {
            id: carId
        },
        success: function(response) {
            
            showToast(response.message, response.status);
            
            // 
            if(response.status == 'success'){
            setTimeout(function() {
                window.location.href = window.baseUrl + '/carbooking';
            }, 2000);
            }
            // 5000 ms = 5 seconds
            
        },
        error: function(xhr, status, error) {
            alert('An error occurred while booking the car.');
            console.error(xhr.responseText);
        },
        complete: function() {
            // Reset button in case you don’t reload
            $button.html(originalBtnText);
            $button.prop('disabled', false);
        }
    });

    
});
});
