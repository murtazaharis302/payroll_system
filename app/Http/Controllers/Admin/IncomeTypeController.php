<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\IncomeType;
use Illuminate\Http\Request;

class IncomeTypeController extends Controller
{
    public function index(Request $request)
    {
        $companyId = getActiveCompanyId();
        $query = IncomeType::forCompany($companyId)
            ->with(['company', 'creator']);

        if ($request->filled('search')) {
            $search = strtolower($request->search);
            $query->where(function ($q) use ($search) {
                $q->whereRaw('LOWER(name) LIKE ?', ["%{$search}%"])
                  ->orWhereRaw('LOWER(code) LIKE ?', ["%{$search}%"]);
            });
        }

        $incomeTypes = $query->orderBy('created_at', 'desc')->paginate(20);
        return view('admin.income_type.index', compact('incomeTypes'));
    }

    public function create()
    {
        return view('admin.income_type.create');
    }

    public function store(Request $request)
    {
        $user = auth()->user();
        $companyId = getActiveCompanyId();

        $validatedData = $request->validate([
            'name'        => 'required|string|max:255',
            'code'        => 'nullable|string|max:50|unique:income_types,code',
            'description' => 'nullable|string',
            'is_taxable'  => 'required|boolean',
            'is_active'   => 'required|boolean',
        ]);

        if (empty($validatedData['code'])) {
            $validatedData['code'] = IncomeType::generateNextCode($validatedData['name'] ?? null);
        }

        $validatedData['company_id'] = $companyId;
        $validatedData['created_by'] = $user?->id;
        unset($validatedData['system_key']);

        $newIncomeType = IncomeType::create($validatedData);

        // Auto-assign to all active employees in this company (Issue #32)
        if ($companyId) {
            $activeEmployees = \App\Models\EmployeeMaster::where('company_id', $companyId)
                ->where('status', 'active')
                ->pluck('id');

            $today = now()->toDateString();
            $rows = [];
            foreach ($activeEmployees as $empId) {
                // Only insert if not already assigned
                $alreadyExists = \App\Models\EmpIncome::where('employee_id', $empId)
                    ->where('income_type_id', $newIncomeType->id)
                    ->exists();
                if (!$alreadyExists) {
                    $rows[] = [
                        'employee_id'    => $empId,
                        'income_type_id' => $newIncomeType->id,
                        'rate'           => 0.00,
                        'is_active'      => true,
                        'effective_date' => $today,
                        'frequency'      => 'monthly',
                        'created_at'     => now(),
                        'updated_at'     => now(),
                    ];
                }
            }
            if (!empty($rows)) {
                \App\Models\EmpIncome::insert($rows);
            }
        }

        return redirect()->to(panelRoute('income-types.index'))->with('statusSuccess', 'Income Type created and automatically assigned to all active employees.');
    }

    public function edit($id)
    {
        $companyId = getActiveCompanyId();
        $incomeType = IncomeType::findOrFail($id);

        if ($companyId && $incomeType->company_id && (int) $incomeType->company_id !== (int) $companyId) {
            abort(403, 'Unauthorized access to this income type.');
        }

        return view('admin.income_type.edit', compact('incomeType'));
    }

    public function update(Request $request, $id)
    {
        $companyId = getActiveCompanyId();
        $user = auth()->user();
        $incomeType = IncomeType::findOrFail($id);

        if ($companyId && $incomeType->company_id && (int) $incomeType->company_id !== (int) $companyId) {
            abort(403, 'Unauthorized access to update this income type.');
        }

        if ($companyId && $incomeType->company_id === null && $user->role !== 'admin') {
            abort(403, 'System default income types can only be modified by Super Admin.');
        }

        $validatedData = $request->validate([
            'name'        => 'required|string|max:255',
            'code'        => 'required|string|max:50|unique:income_types,code,' . $id,
            'description' => 'nullable|string',
            'is_taxable'  => 'required|boolean',
            'is_active'   => 'required|boolean',
        ]);

        $validatedData['updated_by'] = $user?->id;
        unset($validatedData['system_key']);

        $incomeType->update($validatedData);

        return redirect()->to(panelRoute('income-types.index'))->with('statusSuccess', 'Income Type updated successfully.');
    }

    public function destroy($id)
    {
        $companyId = getActiveCompanyId();
        $user = auth()->user();
        $incomeType = IncomeType::findOrFail($id);

        if ($incomeType->system_key === 'basic') {
            abort(403, 'The core Basic Pay income type is reserved and cannot be deleted.');
        }

        if ($companyId && $incomeType->company_id && (int) $incomeType->company_id !== (int) $companyId) {
            abort(403, 'Unauthorized access to delete this income type.');
        }

        if ($companyId && $incomeType->company_id === null && $user->role !== 'admin') {
            abort(403, 'System default income types cannot be deleted by Company Admin.');
        }

        if ($incomeType->empIncomes()->count() > 0) {
            return redirect()->to(panelRoute('income-types.index'))
                ->with('statusDanger', 'Cannot delete income type that is assigned to employees.');
        }

        $incomeType->delete();

        return redirect()->to(panelRoute('income-types.index'))->with('statusSuccess', 'Income Type deleted successfully.');
    }

    public function toggleStatus($id)
    {
        $companyId = getActiveCompanyId();
        $incomeType = IncomeType::findOrFail($id);

        if ($incomeType->system_key === 'basic') {
            abort(403, 'The core Basic Pay income type is reserved and cannot be toggled off.');
        }

        if ($companyId && $incomeType->company_id && (int) $incomeType->company_id !== (int) $companyId) {
            abort(403, 'Unauthorized access to toggle status for this income type.');
        }

        $incomeType->is_active = !$incomeType->is_active;
        $incomeType->updated_by = auth()->id();
        $incomeType->save();

        return redirect()->back()->with('statusSuccess', 'Income type status updated successfully.');
    }
}
