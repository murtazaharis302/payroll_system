<!DOCTYPE html>
<html>
<head>
    <title>Company Approved</title>
</head>
<body>
    <h2>Dear {{ $company->name }},</h2>
    <p>We are pleased to inform you that your company has been approved.</p>
    <p>You can now access your account and enjoy our services.</p>
    <p>If you have any questions, feel free to contact us.</p>
    <br>
    <p>Best Regards,</p>
    <p>{{ config('app.name') }}</p>
</body>
</html>
