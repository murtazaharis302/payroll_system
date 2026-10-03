<?php

use App\Http\Middleware\AdminMiddleware;
use App\Http\Middleware\UserMiddleware;
use App\Http\Middleware\CompanyAuth;
use App\Http\Middleware\IP_Check_Middleware;
use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;
use Illuminate\Http\Request;

return Application::configure(basePath: dirname(__DIR__))
    ->withRouting(
        web: __DIR__.'/../routes/web.php',
        api: __DIR__.'/../routes/api.php',
        commands: __DIR__.'/../routes/console.php',
        health: '/up',
    )
    ->withMiddleware(function (Middleware $middleware) {
        $middleware->alias([
            'IsAdmin' => AdminMiddleware::class,
            'IsUser' => UserMiddleware::class,
            'IPCheck' => IP_Check_Middleware::class,
            'CompanyAuth' => CompanyAuth::class,  
            'SuperAdmin' => \App\Http\Middleware\SuperAdminMiddleware::class,
            'CheckMenuAccess' => \App\Http\Middleware\CheckMenuAccess::class,
        ]);

        $middleware->redirectGuestsTo(function (Request $request) {
            if ($request->is('company', 'company/*')) {
                return route('companyLoginForm');
            }
            if ($request->is('employee', 'employee/*')) {
                return route('employeeLoginForm');
            }
            if ($request->is('hr', 'hr/*')) {
                return route('hrLoginForm');
            }
            return route('login');
        });
    })
    ->withExceptions(function (Exceptions $exceptions) {
        //
    })->create();
   


