<?php

namespace App\Http\Controllers\Admin;
use App\Http\Controllers\Controller;
use App\Mail\CompanyCreated;
use App\Mail\CompanyStatusMail;
use App\Mail\CompanyVerificationMail;
use App\Models\Employee;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\DB;
use App\Mail\CompanyApprovedMail;

use App\Services\CompanyProvisioningService;
use Illuminate\Http\Request;
use App\Models\Company;
use App\Models\User;
use SebastianBergmann\Environment\Console;
use Auth;

class companyController extends Controller
{
    public function showLoginForm()
        {
            return view('company.login');
        }
    public function redirectToCompanyLogin()
        {
            return redirect('company/login');
        }
    /**
     * Display a listing of the resource.
     */
    public function index()
    {
        // if(Auth::user()->role == 'company'){
        //     $companies = company::where('user_id', Auth::id() )->orderBy('created_at', 'desc')->paginate(20);
        //     return view('admin.company.index', compact('companies'));
        // }
        $companies = company::where('status','1')->orderBy('created_at', 'desc')->paginate(20);
        return view('admin.company.index', compact('companies'));
    }

    /**
     * Show the form for creating a new resource.
     */
    public function create()
    {
        return view('admin.company.create');
    }

    /**
     * Store a newly created resource in storage.
     */
    public function store(Request $request, CompanyProvisioningService $provisioningService)
    {
        $validatedData = $request->validate([
            'name' => 'required',
            'companyName' => 'required|unique:companies,name',
            'company_code' => 'nullable|string|max:50|unique:companies,company_code',
            'email' => 'required|email|unique:users,email',
            'companyEmail' => 'required|email|unique:companies,email',
            'password' => 'required',
            'phone' => 'required',
            'website' => 'required',
            'address' => 'nullable|string|max:500',
        ]);
        
        $result = $provisioningService->provision($validatedData);

        return redirect()->route('companies')->with('status', 'Company Added Successfully with Company Code: ' . $result['company']->company_code);
    }

    /**
     * Display the specified resource.
     */
    public function show(string $id)
    {
        //
    }

    /**
     * Show the form for editing the specified resource.
     */
    public function edit(string $id)
    {
        $company = company::find($id);
        return view('admin.company.edit', compact('company'));
    }

    /**
     * Update the specified resource in storage.
     */
    public function update(Request $request, string $id)
    {
        $validatedData = $request->validate([
            'name' => 'required',
            'company_code' => 'nullable|string|max:50|unique:companies,company_code,' . $id,
            'email' => 'required|email',
            'phone' => 'required',
            'website' => 'required',
            'address' => 'nullable|string|max:500',
        ]);

        $company = company::find($id);
        $company->name = $validatedData['name'];
        if (!empty($request->input('company_code'))) {
            $company->company_code = strtoupper(trim($request->input('company_code')));
        } elseif (empty($company->company_code)) {
            $company->company_code = Company::generateCompanyCode();
        }
        $company->email = $validatedData['email'];
        $company->phone = $validatedData['phone'];
        $company->website = $validatedData['website'];
        $company->address = $validatedData['address'] ?? null;
        $company->update();

        return redirect()->route('companies')->with('status', 'Company Record Updated Successfully. Company Code: ' . $company->company_code);
    }

    public function deactivatedCompanies()
    {
        $companies = company::where('status','2')->orderBy('created_at', 'desc')->paginate(20);
        return view('admin.company.deactivated', compact('companies'));
    }
    public function deactivate(string $id)
    {
        $company = company::find($id);
        $company->status = '2';
        $company->save();
        $user = User::find($company->user_id);
        $user->status = '2';
        $user->save();
        $eUserIds = Employee::where('owner_user_id', $company->user_id)->pluck('e_user_id');
        Employee::where('owner_user_id', $company->user_id)->update(['status' => '2']);
        User::whereIn('id', $eUserIds)->update(['status' => '2']);
        
        if (config('mail.notifications_enabled', true)) {
            try {
                DB::afterCommit(function () use ($company) {
                    Mail::to($company->user->email)->queue(new CompanyStatusMail($company, 'inactive'));
                });
            } catch (\Exception $e) {
                Log::error('Company deactivation email queuing failed: ' . $e->getMessage());
            }
        }
        return redirect()->route('companies')-> with('statusDanger','Company Deactivated Successfully.');
    }
    /**
     * Remove the specified resource from storage.
     */
    public function activateCompany($id){
        $company = company::find($id);
        $company->status = '1';
        $company->save();
        $user = User::find($company->user_id);
        $user->status = '1';
        $user->save();
        $eUserIds = Employee::where('owner_user_id', $company->user_id)->pluck('e_user_id');
        Employee::where('owner_user_id', $company->user_id)->update(['status' => '1']);
        User::whereIn('id', $eUserIds)->update(['status' => '1']);
        
        if (config('mail.notifications_enabled', true)) {
            try {
                DB::afterCommit(function () use ($company) {
                    Mail::to($company->user->email)->queue(new CompanyStatusMail($company, 'active'));
                });
            } catch (\Exception $e) {
                Log::error('Company activation email queuing failed: ' . $e->getMessage());
            }
        }
        return redirect()->route('deactivatedCompanies')-> with('status','Company Activated Successfully.');
    }
    public function pending()
        {
            $companies = company::where('status','0')->orderBy('created_at', 'desc')->paginate(20);
            return view('admin.company.pending', compact('companies'));
        }
    public function destroyPending(string $id)
        {
            $company = company::find($id);
            $company->delete();
            $user = User::find($company->user_id);
            $user->delete();
            
            return redirect()->route('pending')-> with('statusDanger','Company Data Deleted Successfully.');
        }
    public function aprovePending($id)
        {
            $company = company::find($id);
            $company->status = 1;
            $company->update();
          
            // Get Company Owner Email
            $user = User::find($company->user_id);
            $user->status = 1;
            $user->update();
            
            // Send Confirmation Email
            if (config('mail.notifications_enabled', true)) {
                try {
                    DB::afterCommit(function () use ($user, $company) {
                        Mail::to($user->email)->queue(new CompanyApprovedMail($company));
                    });
                } catch (\Exception $e) {
                    Log::error('Company approval email queuing failed: ' . $e->getMessage());
                }
            }

            return redirect()->route('pending')->with('status', 'Company ' . $company->name . ' has been approved.');
        }
}
