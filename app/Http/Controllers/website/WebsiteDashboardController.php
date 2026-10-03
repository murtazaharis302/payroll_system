<?php

namespace App\Http\Controllers\website;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

class WebsiteDashboardController extends Controller
{
    public function index()
    {
        $user = Auth::user();
        return view('website.dashboard', compact('user'));
    }
}
