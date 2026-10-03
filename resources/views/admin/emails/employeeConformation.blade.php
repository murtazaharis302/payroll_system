<!DOCTYPE html>
<html>
<head>
    <meta charset="UTF-8">
    <title>Login Details</title>
</head>
<body style="font-family: Arial, sans-serif; background-color: #f4f4f4; padding: 20px;">

    <table width="100%" style="max-width: 600px; margin: auto; background-color: #ffffff; border-radius: 8px; overflow: hidden; box-shadow: 0 0 10px rgba(0,0,0,0.1);">
        <tr style="background-color: #007bff; color: white;">
            <td style="padding: 20px; text-align: center;">
                <h2>Welcome to Payroll System</h2>
            </td>
        </tr>
        <tr>
            <td style="padding: 20px;">
                <p>Dear {{ $user->name ?? 'Employee' }},</p>

                <p>Your account has been created. Please click the link below to set up your account password:</p>

                <p style="text-align: center; margin: 25px 0;">
                    <a href="{{ $setupUrl }}" style="background-color: #007bff; color: #ffffff; padding: 12px 24px; text-decoration: none; border-radius: 4px; display: inline-block; font-weight: bold;">Set Up Password</a>
                </p>

                <p style="font-size: 13px; color: #666;">This single-use setup link will expire in 60 minutes. If you did not request an account, please ignore this email.</p>

                <p>Best regards,<br>
                The Payroll System Team</p>
            </td>
        </tr>
        <tr style="background-color: #f0f0f0; text-align: center; font-size: 12px;">
            <td style="padding: 15px;">
                &copy; {{ date('Y') }} Payroll System. All rights reserved.
            </td>
        </tr>
    </table>

</body>
</html>
