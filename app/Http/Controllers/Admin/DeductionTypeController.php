<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\DeductionType;
use Illuminate\Http\Request;

class DeductionTypeController extends Controller
{
    public function index(Request $request)
    {
        $companyId = getActiveCompanyId();
        $query = DeductionType::forCompany($companyId)->with(['company', 'creator']);

        if ($request->filled('search')) {
            $search = strtolower($request->search);
            $query->where(function ($q) use ($search) {
                $q->whereRaw('LOWER(name) LIKE ?', ["%{$search}%"])
                  ->orWhereRaw('LOWER(code) LIKE ?', ["%{$search}%"]);
            });
        }

        $deductionTypes = $query->orderBy('created_at', 'desc')->paginate(20);
        return view('admin.deduction_type.index', compact('deductionTypes'));
    }

    public function create()
    {
        return view('admin.deduction_type.create');
    }

    public function store(Request $request)
    {
        $user = auth()->user();
        $companyId = getActiveCompanyId();

        $validatedData = $request->validate([
            'name'        => 'required|string|max:255',
            'code'        => 'nullable|string|max:50|unique:deduction_types,code',
            'description' => 'nullable|string',
            'type'        => 'required|in:statutory,loan,other',
            'is_taxable'  => 'nullable|boolean',
            'is_active'   => 'required|boolean',
        ]);

        if (empty($validatedData['code'])) {
            $validatedData['code'] = DeductionType::generateNextCode($validatedData['name'] ?? null);
        }

        $validatedData['company_id'] = $companyId;
        $validatedData['created_by'] = $user?->id;
        $validatedData['is_taxable'] = $request->has('is_taxable') ? (bool) $request->is_taxable : false;
        unset($validatedData['system_key']);

        $newDeductionType = DeductionType::create($validatedData);

        // Auto-assign to all active employees in this company (Issue #32)
        if ($companyId) {
            $activeEmployees = \App\Models\EmployeeMaster::where('company_id', $companyId)
                ->where('status', 'active')
                ->pluck('id');

            $today = now()->toDateString();
            $rows = [];
            foreach ($activeEmployees as $empId) {
                $alreadyExists = \App\Models\EmpDeduction::where('employee_id', $empId)
                    ->where('deduction_type_id', $newDeductionType->id)
                    ->exists();
                if (!$alreadyExists) {
                    $rows[] = [
                        'employee_id'       => $empId,
                        'deduction_type_id' => $newDeductionType->id,
                        'rate'              => 0.00,
                        'is_active'         => true,
                        'effective_date'    => $today,
                        'start_date'        => $today,
                        'end_date'          => null,
                        'frequency'         => 'monthly',
                        'created_at'        => now(),
                        'updated_at'        => now(),
                    ];
                }
            }
            if (!empty($rows)) {
                \App\Models\EmpDeduction::insert($rows);
            }
        }

        return redirect()->to(panelRoute('deduction-types.index'))->with('statusSuccess', 'Deduction Type created and automatically assigned to all active employees.');
    }

    public function edit($id)
    {
        $companyId = getActiveCompanyId();
        $deductionType = DeductionType::findOrFail($id);

        if ($companyId && $deductionType->company_id && (int) $deductionType->company_id !== (int) $companyId) {
            abort(403, 'Unauthorized access to this deduction type.');
        }

        return view('admin.deduction_type.edit', compact('deductionType'));
    }

    public function update(Request $request, $id)
    {
        $companyId = getActiveCompanyId();
        $user = auth()->user();
        $deductionType = DeductionType::findOrFail($id);

        if ($companyId && $deductionType->company_id && (int) $deductionType->company_id !== (int) $companyId) {
            abort(403, 'Unauthorized access to update this deduction type.');
        }

        if ($companyId && $deductionType->company_id === null && $user->role !== 'admin') {
            abort(403, 'System default deduction types can only be modified by Super Admin.');
        }

        $validatedData = $request->validate([
            'name'        => 'required|string|max:255',
            'code'        => 'required|string|max:50|unique:deduction_types,code,' . $id,
            'description' => 'nullable|string',
            'type'        => 'required|in:statutory,loan,other',
            'is_taxable'  => 'nullable|boolean',
            'is_active'   => 'required|boolean',
        ]);

        $validatedData['updated_by'] = $user?->id;
        $validatedData['is_taxable'] = $request->has('is_taxable') ? (bool) $request->is_taxable : false;
        unset($validatedData['system_key']);

        $deductionType->update($validatedData);

        return redirect()->to(panelRoute('deduction-types.index'))->with('statusSuccess', 'Deduction Type updated successfully.');
    }

    public function destroy($id)
    {
        $companyId = getActiveCompanyId();
        $user = auth()->user();
        $deductionType = DeductionType::findOrFail($id);

        if ($deductionType->isStatutory()) {
            abort(403, 'Core statutory deduction types cannot be deleted.');
        }

        if ($companyId && $deductionType->company_id && (int) $deductionType->company_id !== (int) $companyId) {
            abort(403, 'Unauthorized access to delete this deduction type.');
        }

        if ($companyId && $deductionType->company_id === null && $user->role !== 'admin') {
            abort(403, 'System default deduction types cannot be deleted by Company Admin.');
        }

        if ($deductionType->empDeductions()->count() > 0) {
            return redirect()->to(panelRoute('deduction-types.index'))
                ->with('statusDanger', 'Cannot delete deduction type that is assigned to employees.');
        }

        $deductionType->delete();

        return redirect()->to(panelRoute('deduction-types.index'))->with('statusSuccess', 'Deduction Type deleted successfully.');
    }

    public function toggleStatus($id)
    {
        $companyId = getActiveCompanyId();
        $deductionType = DeductionType::findOrFail($id);

        if ($companyId && $deductionType->company_id && (int) $deductionType->company_id !== (int) $companyId) {
            abort(403, 'Unauthorized access to toggle status for this deduction type.');
        }

        $deductionType->is_active = !$deductionType->is_active;
        $deductionType->updated_by = auth()->id();
        $deductionType->save();

        return redirect()->back()->with('statusSuccess', 'Deduction type status updated successfully.');
    }
}
