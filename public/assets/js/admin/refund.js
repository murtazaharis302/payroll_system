$(document).on('click', '#refund', function() {
    const bookingId = $(this).data('booking-id');
    const userReason = $(this).data('user-reason');
    const bookingTotalPrice = $(this).data('booking-amount');
    $('#refund-booking-id').val(bookingId);
    $('#user-reason').val(userReason);
    $('#refund_amount').val(bookingTotalPrice);
  });
$(document).on('change', '#refunded_reason', function() {
    let refunded_reason = $(this).val();
    let $otherField = $('#refunded_reason_other');

    if (refunded_reason === 'other') {
        $otherField.removeClass('d-none').attr('required', true);
    } else {
        $otherField.addClass('d-none').removeAttr('required');
    }
});


$(document).ready(function () {
    $('#refundForm').on('submit', function (e) {
        e.preventDefault();
       
        let form = $(this);
        let url = form.attr('action');
        let formData = form.serialize();

        $.ajax({
            type: 'POST',
            url: url,
            data: formData,
            success: function (response) {
                showToast(response.message,"success");
                $('#refundModal').modal('hide');
                let pagetitle = $('#page-title').text();
                if(pagetitle == 'Order Detail'){
                    $('#issueRefund').hide();
                }else{
                    $('#issueRefund').html('—');
                }
                form.trigger('reset');
                $('#refunded_by').text(response.refunded_by + ' (' + response.refunded_by_role + ')');
                $('#refunded_notes').text(response.refunded_notes);

                $('#payment_status').text('refunded');
                $('#booking_status').text('refunded');
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
