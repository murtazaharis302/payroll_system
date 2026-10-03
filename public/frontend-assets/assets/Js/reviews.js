$(document).on('click', '#getVehicleId', function() {
    const vehicleId = $(this).data('vehicle-id');
    const bookingId = $(this).data('review-booking-id');
  
    $('#modal_review_vehicle_id').val(vehicleId);
    $('#modal_review_booking_id').val(bookingId);
  });

$(document).ready(function () {
    $('#reviewForm').on('submit', function (e) {
        e.preventDefault();
       
        let form = $(this);
        let url = form.attr('action');
        let formData = form.serialize();

        $.ajax({
            type: 'POST',
            url: url,
            data: formData,
            success: function (response) {
                
                showToast(response.message, response.status);
                $('#reviewModal').modal('hide');
                form.trigger('reset');
                form.find('input[type="radio"]').prop('checked', false);
            },
            error: function (xhr) {
                if (xhr.status === 422) {
                    // Show validation errors
                    let errors = xhr.responseJSON.errors;
                    for (let field in errors) {
                        let input = form.find('[name="' + field + '"]');
                        input.css('border', '1px solid red');
                        // Optionally show error messages below inputs
                        if (!input.next('.error-text').length) {
                            input.after('<div class="text-danger error-text">' + errors[field][0] + '</div>');
                        }
                    }
                } else {
                    alert('Error: ' + (xhr.responseJSON?.message || 'Something went wrong.'));
                }
            }
        });
    });
});
