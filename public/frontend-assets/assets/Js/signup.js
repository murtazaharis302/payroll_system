$.ajaxSetup({
    headers: {
        'X-CSRF-TOKEN': $('meta[name="csrf-token"]').attr('content')
    }
});

// Clear field error state when user starts typing
$(document).on('input', '#usersignup input', function () {
    $(this).closest('.input-group').removeClass('input-error');
});

$(document).on('submit', '#usersignup', function(e) {
    e.preventDefault(); 

    // Reset previous error highlights
    $('#usersignup .input-group').removeClass('input-error');

    var name = $('#name').val();
    var email = $('#email').val();
    var phone = $('#phone').val();
    var password = $('#password').val();
    var confirmPassword = $('#password_confirmation').val();
    var form = $(this);
    var url = form.attr('action');

    // Client-side validation with visual feedback
    if (!name) {
        $('#name').closest('.input-group').addClass('input-error');
        showToast('Name Required.', "error");
        return;
    }
    if (!email || !password) {
        if (!email) {
            $('#email').closest('.input-group').addClass('input-error');
        }
        if (!password) {
            $('#password').closest('.input-group').addClass('input-error');
        }
        showToast('Both email and password are required.', "error");
        return;
    }
    if (password.length < 8) {
        $('#password').closest('.input-group').addClass('input-error');
        showToast('Password must be at least 8 characters.', "error");
        return;
    }
    if (!phone) {
        $('#phone').closest('.input-group').addClass('input-error');
        showToast('Phone Number is required.', "error");
        return;
    }
    if (password !== confirmPassword) {
        $('#password').closest('.input-group').addClass('input-error');
        $('#password_confirmation').closest('.input-group').addClass('input-error');
        showToast('Password and Confirm Password are not Same.', "error");
        return;
    }
    
    $.ajax({
        url: url,
        type: "POST",
        data: {
            name: name,
            email: email,
            phone: phone,
            password: password,
            password_confirmation: confirmPassword,
            _token: $('meta[name="csrf-token"]').attr('content')
        },
        success: function(response) {
            showToast(response.message, response.status);
            
            if (response.status === 'Success' || response.status === 'success') {
                let modal = bootstrap.Modal.getInstance(document.getElementById('registerModal'));
                if (modal) modal.hide();
                document.getElementById('usersignup').reset();
                $('#usersignup .input-group').removeClass('input-error');
            }
        },
        error: function(xhr, status, error) {
            $('#usersignup .input-group').removeClass('input-error');

            if (xhr.status === 422 && xhr.responseJSON) {
                if (xhr.responseJSON.errors) {
                    let errors = xhr.responseJSON.errors;
                    var fieldMap = {
                        name: '#name',
                        email: '#email',
                        phone: '#phone',
                        password: '#password',
                        password_confirmation: '#password_confirmation'
                    };

                    for (let field in errors) {
                        if (errors.hasOwnProperty(field)) {
                            if (fieldMap[field]) {
                                $(fieldMap[field]).closest('.input-group').addClass('input-error');
                            }

                            errors[field].forEach(function(message) {
                                showToast(message, "error");
                            });
                        }
                    }
                } else if (xhr.responseJSON.message) {
                    // Custom non-standard validation message (e.g. duplicate email)
                    $('#email').closest('.input-group').addClass('input-error');
                    showToast(xhr.responseJSON.message, "error");
                }
            } else if (xhr.responseJSON && xhr.responseJSON.message) {
                showToast(xhr.responseJSON.message, "error");
            } else {
                var message = "Something went wrong. Please try again.";
                showToast(message, "error");
            }
        }
    });
});
