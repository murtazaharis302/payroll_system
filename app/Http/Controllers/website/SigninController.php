<?php

namespace App\Http\Controllers\website;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Validation\ValidationException;

class SigninController extends Controller
{
    public function signin(Request $request)
    {
        // Validate the request
        $request->validate([
            'email' => 'required|string|email',
            'password' => 'required|string',
        ]);
    
        $userRole = $request->user_role; // From middleware
        $userStatus = $request->user_status; // From middleware
        
        // Block login if user status is 0
        if ($userStatus == 0) {
            return response()->json([
                'status' => 'Error',
                'message' => 'Please Confirm Your Email From Inbox.',
            ]);
        }
    
        // Proceed with login only if status is active (1)
        if ($userStatus == 1 && Auth::attempt(['email' => $request->email, 'password' => $request->password, 'role'=>$userRole ]) && $userRole == 'user') {
            
            $request->session()->regenerate();
    
            $html = view('website.template.include')->render();
    
            return response()->json([
                'status' => 'Success',
                'message' => 'Login successful!',
                'html' => $html,
            ]);
        }
    
        // Invalid credentials or missing role
        return response()->json([
            'status' => 'Error',
            'message' => 'Invalid credentials.',
        ]);
    }
    
}
