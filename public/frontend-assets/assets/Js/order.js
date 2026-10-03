$.ajaxSetup({
    headers: {
        'X-CSRF-TOKEN': $('meta[name="csrf-token"]').attr('content')
    }
});

$(document).on('submit', '#orderBookingFormSubmit', function(e) {
    e.preventDefault();
    var form = $(this);
    var submitBtn = $('#OrderSubmitBtn');
    var isValid = true;
    // validation for input
    $('#orderBookingFormSubmit input').each(function () {
        var input = $(this);
        if (input.val().trim() === '') {
            input.css('border', '1px solid red');
            isValid = false;
        } else {
            input.css('border', '');
        }
    });
    
    submitBtn.prop('disabled', true).text('Processing...');
        

    if (isValid) {
        // start ajax
        $.ajax({
            url: form.attr('action'),
            method: 'POST',
            data: form.serialize(),
            success: function(response) {
                // console.log(response)
                // toaster alert
                    showToast(response.message, "success");
                    // 
                    if(response.status == false){
                        submitBtn.prop('disabled', false).html('<i class="fa-solid fa-cart-shopping"></i> Process to Checkout');
                    }
                    
                    if (response.status && response.redirect_url) {
                        submitBtn.prop('disabled', true).html('<i class="fa-solid fa-cart-shopping"></i> Process to Checkout');
                        window.location.href = response.redirect_url;
                    }
                           
            },
            error: function(xhr) {
                submitBtn.prop('disabled', false).html('<i class="fa-solid fa-cart-shopping"></i> Process to Checkout');
                if (xhr.status === 422) {
                    let errors = xhr.responseJSON.errors;
                    let errorMsg = '';
                    $.each(errors, function(key, val) {
                        errorMsg += val[0] + '\n';
                    });
                    // alert(errorMsg);
                    showToast(errorMsg, "error");
                } else {
                    var message = 'An unexpected error occurred.';
                    showToast(message, "error");
                }
            }
        });
        // end ajax
    } else {

        let toast = {
            title: "Alert",
            message: 'Please fill all required fields correctly.',
            status: 'Error',
            timeout: 5000
        }
        Toast.create(toast);
        submitBtn.prop('disabled', false).html('<i class="fa-solid fa-cart-shopping"></i> Process to Checkout');
       
    }
    
});