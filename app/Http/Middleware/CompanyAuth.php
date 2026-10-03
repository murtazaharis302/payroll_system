<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;
use Illuminate\Support\Facades\Auth;
class CompanyAuth
{
    /**
     * Handle an incoming request.
     *
     * @param  \Closure(\Illuminate\Http\Request): (\Symfony\Component\HttpFoundation\Response)  $next
     */
    public function handle(Request $request, Closure $next): Response
    {
        
        if(!Auth::check())
            {
                return redirect('company')->with('statusDanger', 'Please Login');
            }

        $tenantRoles = ['admin', 'company', 'company_admin', 'hr', 'employee'];
        if (!in_array(Auth::user()->role, $tenantRoles, true)) {
            abort(403, 'Forbidden');
        }

        return $next($request);
    }
}
