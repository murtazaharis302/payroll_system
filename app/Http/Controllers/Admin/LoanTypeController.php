<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Company;
use App\Models\LoanType;
use App\Models\Role;
use Illuminate\Http\Request;

class LoanTypeController extends Controller
{
    protected function checkRoleAccess(): void
    {
        $user = auth()->user();
        if ($user && !\App\Models\MenuRoleAccess::hasModuleAccess($user, 'loan_types')) {
            abort(403, 'Unauthorized access to loan setup configurations.');
        }
    }

    public function index(Request $request)
    {
        $this->checkRoleAccess();

        $currentUser = auth()->user();
        $isSuperAdmin = ($currentUser->role === 'admin' || $currentUser->role_id === Role::SUPER_ADMIN);

        $companies = collect();
        if ($isSuperAdmin) {
            $companies = Company::where('status', 1)->orderBy('name')->get();
        }

        $requestedCompanyId = $request->input('company_id');
        if ($isSuperAdmin) {
            $companyId = $requestedCompanyId ? (int) $requestedCompanyId : null;
        } else {
            $companyId = getActiveCompanyId() ?: $currentUser->company_id;
        }

        $query = LoanType::query()->with(['company', 'deductionType', 'creator', 'updater']);

        if ($companyId) {
            $query->where(function ($q) use ($companyId) {
                $q->where('company_id', $companyId)
                  ->orWhereNull('company_id');
            });
        }

        if ($request->filled('search')) {
            $search = strtolower($request->search);
            $query->whereRaw('LOWER(name) LIKE ?', ["%{$search}%"]);
        }

        $loanTypes = $query->orderBy('name', 'asc')->paginate(20);

        $deductionTypes = \App\Models\DeductionType::withoutGlobalScopes()
            ->where(function ($q) use ($companyId) {
                if ($companyId) {
                    $q->where('company_id', $companyId)->orWhereNull('company_id');
                }
            })
            ->orderBy('name')
            ->get();

        if ($deductionTypes->isEmpty()) {
            $targetCompanyId = $companyId ?? \App\Models\Company::first()?->id;
            $defaultDeductions = [
                ['name' => 'Cash Advance / Vale', 'code' => 'VALE', 'type' => 'loan'],
                ['name' => 'SSS Loan Deduction', 'code' => 'SSS_LOAN', 'type' => 'loan'],
                ['name' => 'Pag-IBIG Loan Deduction', 'code' => 'PAGIBIG_LOAN', 'type' => 'loan'],
                ['name' => 'Goods Purchase / Merchandise', 'code' => 'GOODS', 'type' => 'loan'],
                ['name' => 'General Loan Deduction', 'code' => 'LOAN', 'type' => 'loan'],
                ['name' => 'Other Loan / Deduction', 'code' => 'OTHER', 'type' => 'deduction'],
            ];
            foreach ($defaultDeductions as $dd) {
                \App\Models\DeductionType::firstOrCreate(
                    ['code' => $dd['code']],
                    [
                        'company_id' => $targetCompanyId,
                        'name' => $dd['name'],
                        'type' => $dd['type'],
                        'is_active' => true,
                        'is_loan_enabled' => true,
                    ]
                );
            }
            $deductionTypes = \App\Models\DeductionType::withoutGlobalScopes()
                ->where(function ($q) use ($companyId) {
                    if ($companyId) {
                        $q->where('company_id', $companyId)->orWhereNull('company_id');
                    }
                })
                ->orderBy('name')
                ->get();
        }

        if ($request->wantsJson() || $request->ajax()) {
            return response()->json([
                'status' => 'success',
                'loan_types' => $loanTypes,
                'deduction_types' => $deductionTypes,
            ]);
        }

        return view('admin.payroll_setup.loan_types.index', compact('loanTypes', 'companies', 'companyId', 'isSuperAdmin', 'deductionTypes'));
    }

    public function store(Request $request)
    {
        $this->checkRoleAccess();

        $currentUser = auth()->user();
        $isSuperAdmin = ($currentUser->role === 'admin' || $currentUser->role_id === Role::SUPER_ADMIN);

        $validated = $request->validate([
            'name' => 'required|string|max:255',
            'company_id' => 'nullable|exists:companies,id',
            'deduction_type_id' => 'required|exists:deduction_types,id',
            'default_amortization_per_cutoff' => 'nullable|numeric|min:0',
            'default_installments_count' => 'nullable|integer|min:1',
            'is_active' => 'nullable|boolean',
        ]);

        if ($isSuperAdmin) {
            $companyId = !empty($validated['company_id']) ? (int) $validated['company_id'] : null;
        } else {
            $companyId = getActiveCompanyId() ?: $currentUser->company_id;
        }

        $deductionType = \App\Models\DeductionType::withoutGlobalScopes()->findOrFail($validated['deduction_type_id']);
        if ($companyId && $deductionType->company_id && (int) $deductionType->company_id !== (int) $companyId) {
            throw \Illuminate\Validation\ValidationException::withMessages([
                'deduction_type_id' => ['The selected deduction type belongs to another company.'],
            ]);
        }

        $deductionType->update(['is_loan_enabled' => true]);

        $loanType = LoanType::create([
            'company_id' => $companyId,
            'deduction_type_id' => $deductionType->id,
            'name' => trim($validated['name']),
            'default_amortization_per_cutoff' => (float) ($validated['default_amortization_per_cutoff'] ?? 0.00),
            'default_installments_count' => !empty($validated['default_installments_count']) ? (int) $validated['default_installments_count'] : null,
            'is_active' => isset($validated['is_active']) ? (bool) $validated['is_active'] : true,
            'created_by' => $currentUser->id,
            'updated_by' => $currentUser->id,
        ]);

        if ($request->wantsJson() || $request->ajax()) {
            return response()->json([
                'status' => 'success',
                'message' => 'Loan type created successfully.',
                'loan_type' => $loanType->load('deductionType'),
            ]);
        }

        return redirect()->back()->with('statusSuccess', 'Loan type created successfully.');
    }

    public function update(Request $request, $id)
    {
        $this->checkRoleAccess();

        $loanType = LoanType::findOrFail($id);
        $currentUser = auth()->user();
        $isSuperAdmin = ($currentUser->role === 'admin' || $currentUser->role_id === Role::SUPER_ADMIN);
        $companyId = getActiveCompanyId() ?: $currentUser->company_id;

        if (!$isSuperAdmin && $loanType->company_id && (int) $loanType->company_id !== (int) $companyId) {
            abort(403, 'Unauthorized to update loan types belonging to another company.');
        }

        $validated = $request->validate([
            'name' => 'required|string|max:255',
            'deduction_type_id' => 'required|exists:deduction_types,id',
            'default_amortization_per_cutoff' => 'nullable|numeric|min:0',
            'default_installments_count' => 'nullable|integer|min:1',
            'is_active' => 'nullable|boolean',
        ]);

        $targetCompanyId = $loanType->company_id ?? $companyId;
        $deductionType = \App\Models\DeductionType::withoutGlobalScopes()->findOrFail($validated['deduction_type_id']);
        if ($targetCompanyId && $deductionType->company_id && (int) $deductionType->company_id !== (int) $targetCompanyId) {
            throw \Illuminate\Validation\ValidationException::withMessages([
                'deduction_type_id' => ['The selected deduction type belongs to another company.'],
            ]);
        }

        $deductionType->update(['is_loan_enabled' => true]);

        $loanType->update([
            'name' => trim($validated['name']),
            'deduction_type_id' => $deductionType->id,
            'default_amortization_per_cutoff' => (float) ($validated['default_amortization_per_cutoff'] ?? 0.00),
            'default_installments_count' => !empty($validated['default_installments_count']) ? (int) $validated['default_installments_count'] : null,
            'is_active' => isset($validated['is_active']) ? (bool) $validated['is_active'] : $loanType->is_active,
            'updated_by' => $currentUser->id,
        ]);

        if ($request->wantsJson() || $request->ajax()) {
            return response()->json([
                'status' => 'success',
                'message' => 'Loan type updated successfully.',
                'loan_type' => $loanType->load('deductionType'),
            ]);
        }

        return redirect()->back()->with('statusSuccess', 'Loan type updated successfully.');
    }

    public function toggleStatus($id)
    {
        $this->checkRoleAccess();

        $loanType = LoanType::findOrFail($id);
        $currentUser = auth()->user();
        $isSuperAdmin = ($currentUser->role === 'admin' || $currentUser->role_id === Role::SUPER_ADMIN);
        $companyId = getActiveCompanyId() ?: $currentUser->company_id;

        if (!$isSuperAdmin && $loanType->company_id && (int) $loanType->company_id !== (int) $companyId) {
            abort(403, 'Unauthorized access.');
        }

        $loanType->is_active = !$loanType->is_active;
        $loanType->updated_by = $currentUser->id;
        $loanType->save();

        if (request()->wantsJson() || request()->ajax()) {
            return response()->json([
                'status' => 'success',
                'message' => 'Status updated successfully.',
                'is_active' => $loanType->is_active,
            ]);
        }

        return redirect()->back()->with('statusSuccess', 'Loan type status updated successfully.');
    }

    public function destroy($id)
    {
        $this->checkRoleAccess();

        $loanType = LoanType::findOrFail($id);
        $currentUser = auth()->user();
        $isSuperAdmin = ($currentUser->role === 'admin' || $currentUser->role_id === Role::SUPER_ADMIN);
        $companyId = getActiveCompanyId() ?: $currentUser->company_id;

        if (!$isSuperAdmin && $loanType->company_id && (int) $loanType->company_id !== (int) $companyId) {
            abort(403, 'Unauthorized access.');
        }

        if ($loanType->empLoans()->count() > 0) {
            if (request()->wantsJson() || request()->ajax()) {
                return response()->json([
                    'status' => 'error',
                    'message' => 'Cannot delete loan type that has assigned employee loans.',
                ], 422);
            }
            return redirect()->back()->with('statusDanger', 'Cannot delete loan type that has assigned employee loans.');
        }

        $loanType->delete();

        if (request()->wantsJson() || request()->ajax()) {
            return response()->json([
                'status' => 'success',
                'message' => 'Loan type deleted successfully.',
            ]);
        }

        return redirect()->back()->with('statusSuccess', 'Loan type deleted successfully.');
    }
}
