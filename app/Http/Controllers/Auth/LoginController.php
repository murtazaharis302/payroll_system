<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use Illuminate\Foundation\Auth\AuthenticatesUsers;
use Illuminate\Support\Facades\Auth;
use Illuminate\Http\Request;
use App\Models\Company;
use App\Models\Employee;
use App\Models\User;
use Illuminate\Http\JsonResponse;
use Illuminate\Validation\ValidationException;

class LoginController extends Controller
{
    /*
    |--------------------------------------------------------------------------
    | Login Controller
    |--------------------------------------------------------------------------
    |
    | This controller handles authenticating users for the application and
    | redirecting them to your home screen. The controller uses a trait
    | to conveniently provide its functionality to your applications.
    |
    */

    use AuthenticatesUsers;

    /**
     * Where to redirect users after login.
     *
     * @var string
     */
    protected function redirectTo()
    {
        $user = Auth::user();
    
        if ($user->role === 'admin') {
            if ($user->status == 1) {
                return 'admin/dashboard';
            } else {
                return 'admin/login';
            }
        }
    
        if ($user->role === 'company') {
            if ($user->status == 1) {
                return 'company/dashboard';
            } else {
                return 'company/login';
            }
        }

        if ($user->role === 'employee') {
            if ($user->status == 1) {
                return 'employee/dashboard';
            } else {
                return 'employee/login';
            }
        }

        if ($user->role === 'hr') {
            if ($user->status != 1) {
                return session('login_portal') === 'employee' ? 'employee/login' : 'hr/login';
            }

            $this->setStaffSessionContext($user);

            if (session('login_portal') === 'employee') {
                return 'employee/dashboard';
            }

            return 'hr/dashboard';
        }
    
        // Default redirect path if role is not recognized
        return '/';
    }

    /**
     * Always redirect to the correct panel after login.
     * Ignores session "intended" URL when it belongs to another panel
     * (e.g. admin login should not land on /employee/dashboard).
     */
    protected function sendLoginResponse(Request $request)
    {
        $request->session()->regenerate();

        $this->clearLoginAttempts($request);

        if ($response = $this->authenticated($request, $this->guard()->user())) {
            return $response;
        }

        if ($request->wantsJson()) {
            return new JsonResponse([], 204);
        }

        $defaultPath = $this->redirectPath();
        $intended = $request->session()->pull('url.intended');

        if ($intended && $this->urlMatchesUserPanel($intended)) {
            return redirect()->to($intended);
        }

        return redirect()->to($defaultPath);
    }

    protected function urlMatchesUserPanel(string $url): bool
    {
        $prefix = panelPrefix();
        $path = parse_url($url, PHP_URL_PATH) ?? '';

        return (bool) preg_match('#/' . preg_quote($prefix, '#') . '(/|$)#', $path);
    }
    
    /**
     * Handle a login request to the application.
     *
     * @param  \Illuminate\Http\Request  $request
     * @return \Illuminate\Http\RedirectResponse|\Illuminate\Http\Response|\Illuminate\Http\JsonResponse
     *
     * @throws \Illuminate\Validation\ValidationException
     */
    public function login(Request $request)
    {
        $this->validateLogin($request);

        // Determine expected role for current login route
        $portalRole = null;
        if ($request->is('admin/login') || $request->routeIs('login')) {
            $portalRole = 'admin';
        } elseif ($request->is('company/login') || $request->routeIs('companyLogin')) {
            $portalRole = 'company';
        } elseif ($request->is('hr/login') || $request->routeIs('hrLogin')) {
            $portalRole = 'hr';
        } elseif ($request->is('employee/login') || $request->routeIs('employeeLogin')) {
            $portalRole = 'employee';
        }

        if ($portalRole) {
            $request->session()->put('login_portal', $portalRole);
        }

        // Only validate company code for company login route
        if ($request->is('company/login')) {
            // Validate company code
            $company = company::where('company_code', $request->company_code)
                ->where('status', 1) // Only active companies
                ->first();

            if (!$company) {
                throw ValidationException::withMessages([
                    'company_code' => ['The company code is invalid or company is inactive.'],
                ]);
            }
        }

        // If the class is using the ThrottlesLogins trait, we can automatically throttle
        // the login attempts for this application. We'll key this by the username and
        // the IP address of the client making these requests into this application.
        if (method_exists($this, 'hasTooManyLoginAttempts') &&
            $this->hasTooManyLoginAttempts($request)) {
            $this->fireLockoutEvent($request);

            return $this->sendLockoutResponse($request);
        }

        if ($this->attemptLogin($request)) {
            if ($request->hasSession()) {
                $request->session()->put('auth.password_confirmed_at', time());
            }

            // Store company_id in session after successful login (only for company login)
            if ($request->is('company/login') && isset($company)) {
                $request->session()->put('active_company_id', $company->id);
                $request->session()->put('active_company_code', $company->company_code);
                $request->session()->put('active_company_name', $company->name);
            }

            // HR under a company owner
            $loggedInUser = Auth::user();
            if ($loggedInUser && $loggedInUser->role === 'hr') {
                $this->setStaffSessionContext($loggedInUser);
            }

            return $this->sendLoginResponse($request);
        }

        // If the login attempt was unsuccessful we will increment the number of attempts
        // to login and redirect the user back to the login form. Of course, when this
        // user surpasses their maximum number of attempts they will get locked out.
        $this->incrementLoginAttempts($request);

        return $this->sendFailedLoginResponse($request);
    }

    /**
     * Attempt to log the user into the application.
     *
     * @param  \Illuminate\Http\Request  $request
     * @return bool
     */
    protected function attemptLogin(Request $request)
    {
        $portalRole = session('login_portal');
        if (!$portalRole) {
            if ($request->is('admin/login') || $request->routeIs('login')) {
                $portalRole = 'admin';
            } elseif ($request->is('company/login') || $request->routeIs('companyLogin')) {
                $portalRole = 'company';
            } elseif ($request->is('hr/login') || $request->routeIs('hrLogin')) {
                $portalRole = 'hr';
            } elseif ($request->is('employee/login') || $request->routeIs('employeeLogin')) {
                $portalRole = 'employee';
            }
        }

        $user = User::withoutGlobalScopes()->where('email', $request->email)->first();
        if ($user && $portalRole) {
            $isAllowed = ($user->role === $portalRole) || ($portalRole === 'employee' && $user->role === 'hr');
            if (!$isAllowed) {
                return false;
            }
        }

        return $this->guard()->attempt(
            $this->credentials($request), $request->boolean('remember')
        );
    }

    /**
     * Validate the user login request.
     *
     * @param  \Illuminate\Http\Request  $request
     * @return void
     *
     * @throws \Illuminate\Validation\ValidationException
     */
    protected function validateLogin(Request $request)
    {
        $rules = [
            $this->username() => 'required|string',
            'password' => 'required|string',
        ];
        
        // Only require company_code for company login
        if ($request->is('company/login')) {
            $rules['company_code'] = 'required|string';
        }
        
        $request->validate($rules);
    }

    /**
     * Create a new controller instance.
     *
     * @return void
     */
    public function __construct()
    {
        $this->middleware('guest')->except('logout');
        $this->middleware('auth')->only('logout');
    }

    protected function setStaffSessionContext(User $user): void
    {
        $employee = Employee::where('e_user_id', $user->id)->first();
        if (!$employee) {
            return;
        }

        $owner = User::find($employee->owner_user_id);
        if ($owner && $owner->role === 'company') {
            $ownerCompany = company::where('user_id', $owner->id)->first();
            if ($ownerCompany) {
                session([
                    'active_company_id' => $ownerCompany->id,
                    'active_company_code' => $ownerCompany->company_code,
                    'active_company_name' => $ownerCompany->name,
                ]);
            }
        } else {
            session()->forget(['active_company_id', 'active_company_code', 'active_company_name']);
        }
    }
}
