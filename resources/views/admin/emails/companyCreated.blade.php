<!DOCTYPE html>
<html>
<head>
    <title>Company Approved</title>
</head>
<body>
    <h2>Dear {{ $user->name }},</h2>
    <p>We are pleased to inform you that your company has been Created with Name: <b>{{ $company->name }}</b>.</p>
    <p>Once it will get approved you will get another Email.</p>
    <p>If you have any questions, feel free to contact us.</p>
    <br>
    <p>Best Regards,</p>
    <p>{{ config('app.name') }}</p>
</body>
</html>