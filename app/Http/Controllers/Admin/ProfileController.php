<?php

namespace App\Http\Controllers\Admin;

use App\Models\User;
use Illuminate\Http\Request;
use App\Http\Controllers\Controller;
use Illuminate\Support\Facades\Auth;

class ProfileController extends Controller
{
    public function editProfile($id)
    {
        $authUser = auth()->user();
        if (in_array($authUser?->role, ['employee', 'hr'])) {
            return redirect(panelRoute('settings'))->with('statusDanger', 'Editing personal profile details is restricted. You can update your password in Account Settings.');
        }

        $user = User::with(['companies', 'company'])->findOrFail($id);  
        $company = $user->companies ?? $user->company;
        return view('admin.edit_profile', compact('user', 'company'));
    }

    public function updateProfile(Request $request, $id)
    {
        $authUser = auth()->user();
        if (in_array($authUser?->role, ['employee', 'hr'])) {
            return redirect(panelRoute('settings'))->with('statusDanger', 'Editing personal profile details is restricted.');
        }

        $user = User::findOrFail($id);

        $rules = [
            'name' => 'nullable|string|max:255', 
            'phone' => 'nullable|string|max:20',
            'profile_image' => 'nullable|image|mimes:jpeg,png,jpg,gif,svg|max:2048',
        ];

        if ($user->role === 'company') {
            $rules = array_merge($rules, [
                'company_name' => 'nullable|string|max:255',
                'company_email' => 'nullable|email',
                'company_phone' => 'nullable|string|max:20',
                'company_website' => 'nullable|url',
                'company_detail' => 'nullable|string',
                'company_profile' => 'nullable|image|mimes:jpeg,png,jpg,gif,svg|max:2048',
            ]);
        }

        $request->validate($rules);

        if ($request->filled('name')) {
            $user->name = $request->name;
        }
        if ($request->filled('phone')) {
            $user->phone = $request->phone;
        }

        if ($request->hasFile('profile_image')) {
            $image = $request->file('profile_image');
            $imageName = time() . '_' . $image->getClientOriginalName();
            $image->move(public_path('frontend-assets/assets/profile'), $imageName);
            $user->profile_image = 'frontend-assets/assets/profile/' . $imageName;
        }

        $user->save();

        if ($user->role === 'company') {
            $company = $user->companies ?? $user->company;

            if (!$company) {
                $company = new Company();
                $company->user_id = $user->id;
            }

        if ($request->filled('company_name')) {
            $company->name = $request->company_name;
        }
        if ($request->filled('company_email')) {
            $company->email = $request->company_email;
        }
        if ($request->filled('company_phone')) {
            $company->phone = $request->company_phone;
        }
        if ($request->filled('company_website')) {
            $company->website = $request->company_website;
        }
        if ($request->filled('company_detail')) {
            $company->detail = $request->company_detail;
        }

        if ($request->hasFile('company_profile')) {
            $logo = $request->file('company_profile');
            $logoName = time() . '_' . $logo->getClientOriginalName();
            $logo->move(public_path('frontend-assets/assets/companyprofile'), $logoName);
            $company->company_profile = 'frontend-assets/assets/companyprofile/' . $logoName;
        }

        $company->save();
    }

    return redirect()->route('admin.edit_profile', $id)->with('success', 'Profile updated successfully!');
}

}
