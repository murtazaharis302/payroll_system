$.ajaxSetup({
    headers: {
        'X-CSRF-TOKEN': $('meta[name="csrf-token"]').attr('content')
    }
});

$(document).on('submit', '#registerForm', function (e) {
    e.preventDefault();

    var form = $(this);
    var submitBtn = form.find('button[type="submit"]');

    submitBtn.prop('disabled', true).text('Processing...');
    var delay = 4000; 
    $.ajax({
        url: form.attr('action'),
        method: 'POST',
        data: form.serialize(),
        success: function (response) {
            setTimeout(function () {
                submitBtn.prop('disabled', false).text('Submit');
                showToast(response.message, 'success');
                form.trigger('reset');
            }, delay);
        },
        error: function (xhr) {
            submitBtn.prop('disabled', false).text('Submit');

            if (xhr.status === 422) {
                let errors = xhr.responseJSON.errors;
                let errorMsg = '';
                $.each(errors, function (key, val) {
                    errorMsg += val[0] ;
                });
               
            showToast(errorMsg, 'error');
            } else {
                toastr.error('An unexpected error occurred.');
            }
        }
    });
});
