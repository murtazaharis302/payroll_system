<?php

namespace App\Providers;

use Illuminate\Support\ServiceProvider;
use Illuminate\Pagination\Paginator;
use Illuminate\Support\Facades\Gate;
use App\Models\User;
use Illuminate\Support\Facades\View;
use Illuminate\Support\Facades\URL;
class AppServiceProvider extends ServiceProvider
{

    /**
     * Register any application services.
     */
    public function register(): void
    {
        $this->app->extend('url', function ($url, $app) {
            $customUrl = new \App\Routing\CustomUrlGenerator(
                $app['router']->getRoutes(),
                $app->make('request'),
                $app['config']['app.asset_url']
            );

            if ($app->bound('session')) {
                $customUrl->setSessionResolver(fn() => $app['session']);
            }

            $customUrl->setKeyResolver(fn() => [$app['config']['app.key'], ...($app['config']['app.previous_keys'] ?? [])]);

            return $customUrl;
        });
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        if (config('app.env') === 'production') {
            URL::forceScheme('https');
        }
        Paginator::useBootstrap();

        \App\Models\LoanPayment::observe(\App\Observers\LoanPaymentObserver::class);
        \App\Models\LoanRequest::observe(\App\Observers\LoanRequestObserver::class);
        \App\Models\PayHeader::observe(\App\Observers\PayHeaderObserver::class);
        \App\Models\HRAdjustment::observe(\App\Observers\HRAdjustmentObserver::class);
        \App\Models\EmpIncome::observe(\App\Observers\EmpIncomeObserver::class);
        \App\Models\EmpIn::observe(\App\Observers\EmpIncomeObserver::class);
        \App\Models\EmployeeMaster::observe(\App\Observers\EmployeeMasterObserver::class);

        Gate::policy(\App\Models\EmployeeMaster::class, \App\Policies\EmployeeMasterPolicy::class);
        Gate::policy(\App\Models\Employee::class, \App\Policies\EmployeePolicy::class);
        Gate::policy(\App\Models\PayHeader::class, \App\Policies\PayHeaderPolicy::class);
        Gate::policy(\App\Models\PayrollPeriod::class, \App\Policies\PayrollPeriodPolicy::class);
        Gate::policy(\App\Models\EmpLoan::class, \App\Policies\EmpLoanPolicy::class);
        Gate::policy(\App\Models\LoanPayment::class, \App\Policies\LoanPaymentPolicy::class);
        
        \Illuminate\Support\Facades\Blade::if('canMenu', function ($moduleOrRouteName) {
            $user = auth()->user();
            if (!$user) {
                return false;
            }

            $menu = \App\Models\Menu::where('module', $moduleOrRouteName)
                ->orWhere('route_name', $moduleOrRouteName)
                ->orWhere('route', $moduleOrRouteName)
                ->first();

            $moduleKey = $menu ? $menu->module : $moduleOrRouteName;

            return \App\Models\MenuRoleAccess::hasModuleAccess($user, $moduleKey);
        });
       
    }
}
