<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>403 - Restricted Access | Payroll System</title>
    <link rel="shortcut icon" href="{{ asset('/') }}assets/images/favicon.ico">
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700&display=swap" rel="stylesheet">
    <link href="{{ asset('/') }}assets/css/bootstrap.min.css" rel="stylesheet" type="text/css" />
    <link href="{{ asset('/') }}assets/css/icons.min.css" rel="stylesheet" type="text/css" />

    <style>
        body {
            font-family: 'Inter', sans-serif;
            background-color: #0f172a;
            color: #f8fafc;
            min-height: 100vh;
            display: flex;
            align-items: center;
            justify-content: center;
        }
        .error-card {
            background: rgba(30, 41, 59, 0.85);
            border: 1px solid rgba(255, 255, 255, 0.08);
            border-radius: 16px;
            backdrop-filter: blur(16px);
            max-width: 500px;
            width: 100%;
        }
        .icon-circle {
            width: 80px;
            height: 80px;
            border-radius: 50%;
            background: rgba(239, 68, 68, 0.15);
            color: #ef4444;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 38px;
            margin: 0 auto 20px;
        }
    </style>
</head>
<body>
    <div class="container p-3">
        <div class="card error-card mx-auto shadow-lg text-center p-4 p-md-5">
            <div class="icon-circle">
                <i class="fe-shield-off"></i>
            </div>
            <h1 class="fw-bold text-white mb-2 font-24">403 - Access Denied</h1>
            <p class="text-slate-400 font-14 mb-4" style="color: #94a3b8;">
                You do not have permission to access this module or resource. Please contact your company administrator if you believe this is an error.
            </p>
            <div>
                <a href="{{ Auth::check() ? panelRoute('dashboard') : url('/') }}" class="btn btn-primary px-4 py-2 fw-semibold">
                    <i class="fe-home me-1"></i> Return to Dashboard
                </a>
            </div>
        </div>
    </div>
</body>
</html>
