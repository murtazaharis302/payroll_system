<!DOCTYPE html>
<html>
<head>
    <title>Email Confirmed</title>
</head>
<body>
    <h2>Hi {{ $user->name }},</h2>
    <p>Your email has been successfully Activated.</p>
    <p>You can now log in and enjoy our full services.</p>
    <br>
    <p>Thank you,</p>
    <p>{{ config('app.name') }} Team</p>
</body>
</html>
