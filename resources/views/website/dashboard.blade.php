<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>User Dashboard - Payroll</title>
    <style>
        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }
        body {
            font-family: 'Arial', sans-serif;
            background: #f5f5f5;
        }
        .navbar {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            padding: 20px 40px;
            color: white;
            display: flex;
            justify-content: space-between;
            align-items: center;
            box-shadow: 0 2px 10px rgba(0, 0, 0, 0.1);
        }
        .navbar h2 {
            font-size: 24px;
        }
        .navbar .user-info {
            display: flex;
            align-items: center;
            gap: 20px;
        }
        .logout-btn {
            padding: 10px 20px;
            background: rgba(255, 255, 255, 0.2);
            color: white;
            text-decoration: none;
            border-radius: 5px;
            border: 1px solid rgba(255, 255, 255, 0.3);
            transition: all 0.3s;
        }
        .logout-btn:hover {
            background: rgba(255, 255, 255, 0.3);
        }
        .container {
            max-width: 1200px;
            margin: 50px auto;
            padding: 0 20px;
        }
        .welcome-card {
            background: white;
            padding: 60px 40px;
            border-radius: 15px;
            box-shadow: 0 5px 20px rgba(0, 0, 0, 0.1);
            text-align: center;
        }
        .welcome-card h1 {
            font-size: 48px;
            color: #333;
            margin-bottom: 20px;
        }
        .welcome-card .user-name {
            font-size: 32px;
            color: #667eea;
            font-weight: bold;
            margin-bottom: 30px;
        }
        .welcome-card p {
            font-size: 18px;
            color: #666;
            margin-bottom: 40px;
        }
        .stats {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(250px, 1fr));
            gap: 20px;
            margin-top: 40px;
        }
        .stat-card {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            padding: 30px;
            border-radius: 10px;
            color: white;
        }
        .stat-card h3 {
            font-size: 24px;
            margin-bottom: 10px;
        }
        .stat-card p {
            font-size: 16px;
            opacity: 0.9;
        }
    </style>
</head>
<body>
    <div class="navbar">
        <h2>Payroll</h2>
        <div class="user-info">
            <span>{{ $user->name }}</span>
            <form action="{{ route('logout') }}" method="POST" style="display: inline;">
                @csrf
                <button type="submit" class="logout-btn" style="cursor: pointer; background: rgba(255, 255, 255, 0.2); border: 1px solid rgba(255, 255, 255, 0.3);">Logout</button>
            </form>
        </div>
    </div>

    <div class="container">
        <div class="welcome-card">
            <h1>Welcome Back!</h1>
            <div class="user-name">{{ $user->name }}</div>
            <p>Email: {{ $user->email }}</p>
            <p>Role: {{ ucfirst($user->role) }}</p>
            
            @if($user->phone)
                <p>Phone: {{ $user->phone }}</p>
            @endif
        </div>

        <div class="stats">
            <div class="stat-card">
                <h3>Profile</h3>
                <p>Manage your personal information</p>
            </div>
            <div class="stat-card">
                <h3>Settings</h3>
                <p>Customize your preferences</p>
            </div>
            <div class="stat-card">
                <h3>Security</h3>
                <p>Keep your account secure</p>
            </div>
        </div>
    </div>
</body>
</html>
