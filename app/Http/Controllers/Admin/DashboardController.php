<?php

namespace App\Http\Controllers\Admin;
use Carbon\Carbon;
// use App\Models\Car;
use App\Models\User;
use App\Models\Currency;
// use App\Models\Booking;
use App\Models\Company;
// use App\Models\Country;
use App\Models\Language;
// use App\Models\Reminder;
use App\Models\Employee;
use App\Models\EmployeeMaster;
use App\Models\IP_Address;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use App\Http\Controllers\Controller;
use Illuminate\Support\Facades\Auth;
use App\Helpers\FilterHelper;

class DashboardController extends Controller
{
    /**
     * Get company ID for current user
     */
    protected function getCompanyId()
    {
        return getActiveCompanyId();
    }
    
    public function index()
    {
        return redirect('admin/login');
    }

    public function dashboard(Request $request)
    {
        $role = Auth::user()->role;

        if ($role === 'admin') {
            return app(SuperAdminDashboardController::class)->dashboard($request);
        }

        if ($role === 'company' || $role === 'accounting') {
            return app(CompanyAdminDashboardController::class)->dashboard($request);
        }

        if ($role === 'hr') {
            if (session('login_portal') === 'employee') {
                return app(EmployeeDashboardController::class)->dashboard($request);
            }
            return app(HRDashboardController::class)->dashboard($request);
        }

        if ($role === 'employee') {
            return app(EmployeeDashboardController::class)->dashboard($request);
        }

        return redirect()->route('user.dashboard');
    }

    // Booking Dashboard Methods - All Disabled/Removed
    public function bookingDashboard(Request $request)
    {
        return redirect()->to(panelRoute('dashboard'))->with('statusDanger', 'Booking dashboard is not available.');
    }

    public function BookingsOverview(Request $request)
    {
        return response()->json([]);
    }
}







        // public function bookingDashboard(Request $request)
        // {
        //     $companyUserId = $request->user_id ?? null;
        //     $countryId = $request->country_id ?? null;
        //     $startDate = $request->start_date ?? null;
        //     $endDate = $request->end_date ?? null;

        //     $role = Auth::user()->role;
        //     $companies = Company::where('status', 1)->pluck('name', 'user_id');
        //     $countryNames = Country::with('companies')->where('status', 1)->get()->pluck('name','id');

        //     if ($startDate) {
        //         $startDate = date('Y-m-d', strtotime($startDate));
        //     }
        //     if ($endDate) {
        //         $endDate = date('Y-m-d', strtotime($endDate));
        //     }

        //     $applyFilters = function ($query) use ($companyUserId, $startDate, $endDate , $countryId) {
        //     if ($companyUserId) {
        //         $query->whereHas('booking_items.vehicle', function ($q) use ($companyUserId) {
        //             $q->where('user_id', $companyUserId);
        //         });
        //     }

        //     if ($countryId) {
        //         $companyUserIds = Company::where('country_id', $countryId)->pluck('user_id')->toArray();

        //         $query->whereHas('booking_items.vehicle', function ($q) use ($companyUserIds) {
        //             $q->whereIn('user_id', $companyUserIds);
        //         });
        //     }

        //     if ($startDate && $endDate) {
        //         $query->whereDate('created_at', '>=', $startDate)
        //                 ->whereDate('created_at', '<=', $endDate);
        //     }
        //     };


        //     $bookings = Booking::with('user')->orderBy('created_at', 'desc')->take(10)->get();
        //     $today = Carbon::today();
        
        //     $futureConfirmedCount = Booking::where('booking_status', 'confirmed')
        //         ->whereDate('created_at', '>', $today)
        //         ->count();
        
        //     $pendingCount = Booking::where('booking_status', 'pending')->count();
        //     $cancelledCount = Booking::where('booking_status', 'cancelled')->count();
        //     $confirmedCount = Booking::where('booking_status', 'confirmed')->count();
        
        //     return view('admin.bookingDashboard', compact(
        //         'futureConfirmedCount',
        //         'bookings',
        //         'pendingCount',
        //         'cancelledCount',
        //         'confirmedCount',
        //         'companies', 'companyUserId',
        //         'countryNames' , 'countryId'
        //     ));
        // }

    //          public function BookingsOverview(Request $request)
    //     {
    //         $months = $request->query('months', 12);
    //         $data = [];
        
    //         for ($i = 0; $i < $months; $i++) {
    //             $monthDate = Carbon::now()->subMonths($months - 1 - $i);
    //             $month = $monthDate->format('M');
    //             $year = $monthDate->year;
        
    //             $confirmed = Booking::where('booking_status', 'confirmed')
    //                 ->whereMonth('created_at', $monthDate->month)
    //                 ->whereYear('created_at', $year)
    //                 ->count();
        
    //             $cancelled = Booking::where('booking_status', 'cancelled')
    //                 ->whereMonth('created_at', $monthDate->month)
    //                 ->whereYear('created_at', $year)
    //                 ->count();
        
    //             $data[] = [
    //                 'month' => $month,
    //                 'confirmed' => $confirmed,
    //                 'cancelled' => $cancelled,
    //             ];
    //         }
    //         return response()->json($data);
    // }
