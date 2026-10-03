$.ajaxSetup({
    headers: {
        'X-CSRF-TOKEN': $('meta[name="csrf-token"]').attr('content')
    }
});

$(document).on('submit', '#bookingForm', function(e) {
    e.preventDefault();
    var form = $(this);
    var submitBtn = $('#submitBtn');
    var isValid = true;
    // validation for input
    $('#bookingForm input').each(function () {
        var input = $(this);
        if (input.val().trim() === '') {
            input.css('border', '1px solid red');
            isValid = false;
        } else {
            input.css('border', '');
        }
    });
    // validation for select
    $('#bookingForm select').each(function () {
        var input = $(this);
        if (!input.val()) {
            input.css('border', '1px solid red');
            isValid = false;
        } else {
            input.css('border', '');
        }
    });

    if (isValid) {
        submitBtn.prop('disabled', true).text('Processing...');
        // start ajax
        $.ajax({
            url: form.attr('action'),
            method: 'POST',
            data: form.serialize(),
            success: function(response) {
                
                    showToast(response.message, "success");
                    // 
                    submitBtn.prop('disabled', false).text('Order Confirmation');
                    if(response.status){
                        setTimeout(function () {
                            $('.booking-render-page').html(response.html);
                            $('.progress_step_active').addClass('active');
                            $('.update-line-progress-bar').removeClass('progress-bar-filled');
                            $('.update-line-progress-bar').addClass('progress-bar-filled-order');                            
                        }, 2000);
                    }
                           
            },
            error: function(xhr) {
                submitBtn.prop('disabled', false).text('Order Confirmation');
                if (xhr.status === 422) {
                    let errors = xhr.responseJSON.errors;
                    let errorMsg = '';
                    $.each(errors, function(key, val) {
                        errorMsg += val[0] + '\n';
                    });
                    // alert(errorMsg);
                } else {
                    alert('An unexpected error occurred.');
                }
            }
        });
        // end ajax
    } else {

        var message = 'Please fill all required fields correctly.';
         showToast(message, "error");
        submitBtn.prop('disabled', false).text('Order Confirmation');
       
    }
    
});