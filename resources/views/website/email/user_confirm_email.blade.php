<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Confirm Your Email</title>
    <style>
        body {
            font-family: 'Arial', sans-serif;
            background-color: #f4f4f4;
            padding: 30px;
            color: #333;
        }
        .email-container {
            background-color: #ffffff;
            padding: 25px;
            border-radius: 8px;
            max-width: 600px;
            margin: auto;
            box-shadow: 0 4px 6px rgba(0,0,0,0.1);
        }
        .email-header {
            font-size: 20px;
            font-weight: bold;
            margin-bottom: 20px;
        }
        .email-footer {
            margin-top: 30px;
            font-size: 14px;
            color: #777;
        }
        .btn {
            display: inline-block;
            background-color: #3490dc;
            color: #fff !important;
            padding: 10px 18px;
            border-radius: 5px;
            text-decoration: none;
            margin-top: 20px;
        }
        .btn:hover {
            background-color: #2779bd;
        }
    </style>
</head>
<body>
    <div class="email-container">
        <div class="email-header">Hello {{ $user->name }},</div>
        <p>Thank you for registering with {{ config('app.name') }}.</p>
        <p>Please click the button below to confirm your email address and activate your account:</p>

        <a href="{{ $confirmationLink }}" class="btn">Confirm Email</a>

        <p>If you didn’t create an account, no further action is required.</p>

        <div class="email-footer">
            Regards,<br>
            {{ config('app.name') }} Team
        </div>
    </div>
</body>
</html>

