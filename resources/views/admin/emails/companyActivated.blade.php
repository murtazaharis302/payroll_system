<!DOCTYPE html>
<html>
<head>
    <meta charset="UTF-8">
    <title>Company Activated</title>
    <style>
        body { font-family: Arial, sans-serif; background: #f9f9f9; padding: 20px; color: #333; }
        .container { background: #fff; padding: 30px; border-radius: 8px; box-shadow: 0 0 10px rgba(0,0,0,0.1); }
        .footer { margin-top: 30px; font-size: 12px; color: #777; }
    </style>
</head>
<body>
    <div class="container">
        <h2>✅ Company Activated</h2>
        <p>Hello {{ $company->user->name }},</p>

        <p>We’re happy to inform you that your company <strong>{{ $company->name }}</strong> has been <strong>successfully activated</strong> on the <strong>Payroll System</strong> platform.</p>

        <p>You can now manage listings, users, and settings from your dashboard.</p>

        <p>If you have any questions or need assistance, feel free to contact our support team.</p>

        <p>Thank you for being a part of our community!</p>

        <div class="footer">
            — This message was sent by <strong>Payroll System</strong>
        </div>
    </div>
</body>
</html>
