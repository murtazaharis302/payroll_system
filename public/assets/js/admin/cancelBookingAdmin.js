$(document).on('click', '.cancelBookingBtn', function () {
    const bookingId = $(this).data('booking-id');
    $('#modal_booking_id_cancel').val(bookingId);
});

$(document).ready(function () {
    $('#cancelForm').on('submit', function (e) {
        e.preventDefault();
       
        let form = $(this);
        let url = form.attr('action');
        let formData = form.serialize();

        $.ajax({
            type: 'POST',
            url: url,
            data: formData,
            success: function (response) {
                if (response.status === 'success') {
                    let bookingId = $('#modal_booking_id_cancel').val();
                    showToast(response.message, response.status);
                    $('#cancelModal').modal('hide');
                    form.trigger('reset');
                    $('#cancel_note').text(response.note);
                    $('#cancelButton-' + bookingId).replaceWith('<span id="cancelButton-' + bookingId + '"> — </span>');
                    $('#booking_status').text('CANCELLED');
                    $('#pickup_dropoff').replaceWith('<span> — </span>');
                    $('#cancelled_by').text(response.cancelled_by + ' (' + response.cancelled_by_role + ')');
                }
                if(response.status === 'error'){
                    let bookingId = $('#modal_booking_id').val();
                    showToast(response.message, response.status);
                    $('#cancelModal').modal('hide');
                    form.trigger('reset');
                    $('#cancelBookingBtn-' + bookingId).replaceWith('<span id="cancelButton-' + bookingId + '"> - </span>');
                }
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
