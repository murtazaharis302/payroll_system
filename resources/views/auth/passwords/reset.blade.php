<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Set Up Password - Payroll System</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <style>
        body { background-color: #f8fafc; font-family: 'Inter', sans-serif; }
        .reset-card { max-width: 450px; margin: 80px auto; border-radius: 12px; box-shadow: 0 10px 25px rgba(0,0,0,0.05); }
    </style>
</head>
<body>
<div class="container">
    <div class="card reset-card p-4">
        <h4 class="mb-3 text-center fw-bold text-primary">Set Up Account Password</h4>
        <p class="text-muted text-center small mb-4">Enter your email and choose a new secure password for your account.</p>

        @if (session('status'))
            <div class="alert alert-success small mb-3">
                {{ session('status') }}
            </div>
        @endif

        @if ($errors->any())
            <div class="alert alert-danger small mb-3">
                <ul class="mb-0 ps-3">
                    @foreach ($errors->all() as $error)
                        <li>{{ $error }}</li>
                    @endforeach
                </ul>
            </div>
        @endif

        <form method="POST" action="{{ route('password.update') }}">
            @csrf
            <input type="hidden" name="token" value="{{ $token }}">

            <div class="mb-3">
                <label for="email" class="form-label small fw-semibold">Email Address</label>
                <input id="email" type="email" class="form-control" name="email" value="{{ $email ?? old('email') }}" required autofocus readonly>
            </div>

            <div class="mb-3">
                <label for="password" class="form-label small fw-semibold">New Password</label>
                <input id="password" type="password" class="form-control" name="password" required>
            </div>

            <div class="mb-4">
                <label for="password-confirm" class="form-label small fw-semibold">Confirm Password</label>
                <input id="password-confirm" type="password" class="form-control" name="password_confirmation" required>
            </div>

            <button type="submit" class="btn btn-primary w-100 py-2 fw-semibold">Set Password & Save</button>
        </form>
    </div>
</div>
</body>
</html>
