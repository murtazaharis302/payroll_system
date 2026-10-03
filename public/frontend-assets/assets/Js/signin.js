$.ajaxSetup({
    headers: {
        'X-CSRF-TOKEN': $('meta[name="csrf-token"]').attr('content')
    }
});

// Clear field error state when user starts typing
$(document).on('input', '#usersignin input', function () {
    $(this).closest('.input-group').removeClass('input-error');
});

$(document).on('submit', '#usersignin', function(e) {
    e.preventDefault(); 

    console.log('heresignin');


    // Reset previous error highlights
    $('#usersignin .input-group').removeClass('input-error');


    var email = $('#get-email').val();
    var password = $('#get-password').val();
    var form = $(this); // reference to the form
    var url = form.attr('action'); // get the action attribute
    
    // Client-side validation with visual feedback
    if (!email || !password) {

        if (!email) {
            $('#get-email').closest('.input-group').addClass('input-error');
        }
        if (!password) {
            $('#get-password').closest('.input-group').addClass('input-error');
        }


        var message = 'Both email and password are required.';
        showToast(message, "error");
        return;
    }

    $.ajax({
        url: url, // double-check your route name
        type: "POST",
        data: {
            email: email,
            password: password,
            _token: $('meta[name="csrf-token"]').attr('content')// Laravel CSRF token
        },
        success: function(response) {
            
            showToast(response.message, response.status);
            // 
            if (response.status === 'Success') {
                let modal = bootstrap.Modal.getInstance(document.getElementById('loginModal'));
                if (modal) modal.hide();
                $('#usersignin .input-group').removeClass('input-error');
                setTimeout(function() {
                    // Replace the content in #getStartedButton with server-rendered HTML
                    $('#getStartedButton').html(response.html);
                    $('#getStartedButtonMobile').html(response.html);
                }, 1000); // add a slight delay if needed
                window.location.reload();
            }
            
        },
        error: function(xhr, status, error) {
            console.error('Error:', xhr.responseText);

            // On invalid credentials, highlight both fields
            $('#get-email').closest('.input-group').addClass('input-error');
            $('#get-password').closest('.input-group').addClass('input-error');

            // Optional: show a generic error toast if server didn't send one
            if (xhr.responseJSON && xhr.responseJSON.message) {
                showToast(xhr.responseJSON.message, "error");
            }
        }
    });
});
