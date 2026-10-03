<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Hash;

class SettingsController extends Controller
{
    public function index()
    {
        $this->ensureInternalPortalUser();

        return view('employee.settings');
    }

    public function updatePassword(Request $request)
    {
        $this->ensureInternalPortalUser();

        $request->validate([
            'current_password' => 'required|string',
            'new_password' => 'required|string|min:8|confirmed',
        ], [
            'new_password.confirmed' => 'The new password confirmation does not match.',
            'new_password.min' => 'The new password must be at least 8 characters long.',
        ]);

        $user = Auth::user();

        if (! Hash::check($request->current_password, $user->password)) {
            return redirect()->back()
                ->withErrors(['current_password' => 'The current password you entered is incorrect.'])
                ->with('statusDanger', 'The current password you entered is incorrect.');
        }

        if (Hash::check($request->new_password, $user->password)) {
            return redirect()->back()
                ->withErrors(['new_password' => 'The new password must be different from your current password.'])
                ->with('statusDanger', 'The new password must be different from your current password.');
        }

        $user->password = Hash::make($request->new_password);
        $user->save();
        $request->session()->regenerate();

        return redirect()->back()->with('status', 'Password updated successfully!');
    }

    private function ensureInternalPortalUser(): void
    {
        abort_unless(in_array(Auth::user()?->role, ['admin', 'company', 'hr', 'employee'], true), 403);
    }
}
