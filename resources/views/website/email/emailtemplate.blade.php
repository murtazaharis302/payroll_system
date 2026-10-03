
<!DOCTYPE html>
<html>
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>{{ __('messages.email_template') }} | Payroll System</title>
    <style>
        body {
            font-family: Arial, sans-serif;
            background-color: #f4f4f4;
            margin: 0;
            padding: 0;
        }
        .container {
            max-width: 600px;
            margin: 20px auto;
            background: #ffffff;
            padding: 25px;
            border-radius: 10px;
            box-shadow: 0 4px 10px rgba(0, 0, 0, 0.1);
            text-align: center;
        }
        .logo {
            width: 140px;
            margin-bottom: 20px;
        }
        h2 {
            color: #333;
            margin-bottom: 15px;
        }
        .content {
            font-size: 16px;
            color: #555;
            margin-bottom: 20px;
            line-height: 1.6;
        }
        .button {
            display: inline-block;
            padding: 12px 30px;
            background: #f06115;
            color: #ffffff;
            text-decoration: none;
            border-radius: 30px;
            font-weight: bold;
            transition: 0.3s ease;
        }
        .button:hover {
            background: #f06115;
        }
        .support-box {
            background: #f8f8f8;
            padding: 15px;
            margin-top: 20px;
            border-radius: 8px;
        }
        .support-box p {
            margin: 5px 0;
            font-size: 14px;
            color: #666;
        }
        .footer {
            margin-bottom: 2px;
            font-size: 14px;
            color: #888;
        }
        .social-icons a {
            display: inline-block;
            margin: 0 5px;
            text-decoration: none;
        }
        .social-icons img {
            width: 28px;
            height: auto;
            border-radius: 50%;
        }
        @media (max-width: 600px) {
            .container {
                padding: 15px;
            }
            .button {
                padding: 10px 20px;
            }
        }
    </style>
</head>
<body>
    <div class="container">
        <h3 class="text-primary mb-3" style="font-weight: 700;">
            <i class="mdi mdi-account-group"></i> Payroll System
        </h3>
        <p style="font-size: 0.85rem; color: #666;">HR & Payroll Management</p>
        <h2>Welcome to Payroll System, {{ $user->name }}!</h2>
        <p class="content">{{ __('messages.thank_you_for_registering') }}:</p>
        <a href="{{ route('verification.verify', ['id' => $user->id, 'hash' => sha1($user->email)]) }}" class="button">
            {{ __('messages.verify_your_email') }}
        </a>
        
        <div class="support-box">
            <p><strong>{{ __('messages.need_help') }}?</strong></p>
            <p>{{ __('messages.contact_our_support') }} <br> <strong>support@payrollsystem.com</strong></p>
        </div>

        <p class="footer">{{ __('messages.stay_connected_with_us') }}:</p>
        <div class="social-icons">
            <a href="#"><img src="{{ $facebook }}" alt="Facebook"></a>
            <a href="#"><img src="{{ $twitter }}" alt="Twitter"></a>
            <a href="#"><img src="{{ $instagram }}" alt="Instagram"></a>
            <a href="#"><img src="{{ $linkedin }}" alt="LinkedIn"></a>
        </div>
        <hr>
        <div class="footer">© 2025{{ __('messages.all_rights_reserved') }} </div>
    </div>
</body>
</html>
