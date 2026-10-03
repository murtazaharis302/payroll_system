<!DOCTYPE html>
<html>
<head>
    <meta charset="UTF-8">
    <title>Company Deactivated</title>
    <style>
        body { font-family: Arial, sans-serif; background: #f9f9f9; padding: 20px; color: #333; }
        .container { background: #fff; padding: 30px; border-radius: 8px; box-shadow: 0 0 10px rgba(0,0,0,0.1); }
        .footer { margin-top: 30px; font-size: 12px; color: #777; }
    </style>
</head>
<body>
    <div class="container">
        <h2>⚠️ Company Deactivated</h2>
        <p>Hello {{ $company->user->name }},</p>

        <p>We want to let you know that your company <strong>{{ $company->name }}</strong> has been <strong>deactivated</strong> from the <strong>Payroll System</strong> platform.</p>

        <p>This means you currently won't be able to access company features or manage any listings.</p>

        <p>If this was unexpected or you need more information, please contact our support team.</p>

        <p>We’re here to help.</p>

        <div class="footer">
            — This message was sent by <strong>Payroll System</strong> 
        </div>
    </div>
</body>
</html>
