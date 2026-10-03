<?php

namespace App\Http\Controllers\Admin\Concerns;

use App\Http\Controllers\Controller;
use App\Models\Department;
use App\Models\EmployeeMaster;
use App\Models\SubDepartment;
use App\Models\Company;
use App\Models\User;
use App\Models\PayrollPeriod;
use App\Models\EmpIncome;
use App\Models\EmpDeduction;
use App\Models\IncomeType;
use App\Models\DeductionType;
use Illuminate\Http\Request;

trait PayrollEmployeeQuickRecordActions
{
    public function updateQuickRecordAjax(Request $request, $id)
    {
        $this->restrictHrAccess();

        $employee = EmployeeMaster::findOrFail($id);
        $this->authorizeEmployeeAccess($employee);

        $validatedData = $request->validate([
            'employee_code' => 'nullable|string|max:255|unique:employees_master,employee_code,' . $id,
            'first_name' => 'required|string|max:255',
            'middle_name' => 'nullable|string|max:255',
            'last_name' => 'required|string|max:255',
            'email' => 'nullable|email|unique:employees_master,email,' . $id,
            'phone' => 'nullable|string|max:20',
            'date_of_birth' => 'nullable|date',
            'date_hired' => 'nullable|date',
            'position' => 'nullable|string|max:255',
            'status' => 'required|in:active,inactive,resigned',
            'dept_id' => 'required|exists:departments,id',
            'sub_dept_id' => 'nullable|exists:sub_departments,id',
            'acct_num' => 'nullable|string|max:50',
            'atm_num' => 'nullable|string|max:50',
            'biometric_id' => 'nullable|string|max:50',
            'sss_number' => 'nullable|string|max:50',
            'philhealth_number' => 'nullable|string|max:50',
            'pagibig_number' => 'nullable|string|max:50',
            'tin_number' => 'nullable|string|max:50',
            'tax_code' => 'nullable|string|max:20',
            'remarks' => 'nullable|string|max:2000',
            'incomes' => 'nullable|array',
            'incomes.*.id' => 'nullable|integer',
            'incomes.*.income_type_id' => 'required_with:incomes|integer',
            'incomes.*.amount' => 'nullable|numeric|min:0',
            'deductions' => 'nullable|array',
            'deductions.*.id' => 'nullable|integer',
            'deductions.*.deduction_type_id' => 'required_with:deductions|integer',
            'deductions.*.amount' => 'nullable|numeric|min:0',
        ]);

        $validatedData['company_id'] = $employee->company_id;
        $validatedData = $this->normalizeEmployeeData($validatedData);

        if (empty($validatedData['employee_code'])) {
            $validatedData['employee_code'] = $employee->employee_code ?: EmployeeMaster::generateNextEmployeeCode((int) $employee->company_id);
        }

        // Validate Income Types and Income Assignment IDs
        $processedIncomes = [];
        if (isset($request->incomes) && is_array($request->incomes)) {
            foreach ($request->incomes as $incData) {
                if (empty($incData['income_type_id'])) {
                    continue;
                }
                $typeId = (int) $incData['income_type_id'];
                $assignmentId = !empty($incData['id']) ? (int) $incData['id'] : null;

                // Tenant scope check for IncomeType
                $type = IncomeType::query()
                    ->where('id', $typeId)
                    ->where('is_active', true)
                    ->where(function ($q) use ($employee) {
                        $q->whereNull('company_id')
                          ->orWhere('company_id', $employee->company_id);
                    })
                    ->first();

                if (!$type) {
                    return response()->json([
                        'status' => 'error',
                        'message' => 'Unauthorized or invalid income type for this company.'
                    ], 422);
                }

                // If assignment ID provided, verify ownership and type match
                if ($assignmentId) {
                    $empIncRecord = EmpIncome::where('id', $assignmentId)
                        ->where('employee_id', $employee->id)
                        ->first();
                    if (!$empIncRecord) {
                        return response()->json([
                            'status' => 'error',
                            'message' => 'Unauthorized or invalid income assignment ID.'
                        ], 422);
                    }
                    if ((int) $empIncRecord->income_type_id !== $typeId) {
                        return response()->json([
                            'status' => 'error',
                            'message' => 'Income assignment ID does not match the specified income type.'
                        ], 422);
                    }
                }

                // Deterministic deduplication per income_type_id
                $processedIncomes[$typeId] = [
                    'id' => $assignmentId,
                    'amount' => (float) ($incData['amount'] ?? 0),
                ];
            }
        }

        // Validate Deduction Types and Deduction Assignment IDs
        $processedDeductions = [];
        if (isset($request->deductions) && is_array($request->deductions)) {
            foreach ($request->deductions as $decData) {
                if (empty($decData['deduction_type_id'])) {
                    continue;
                }
                $typeId = (int) $decData['deduction_type_id'];
                $assignmentId = !empty($decData['id']) ? (int) $decData['id'] : null;

                // Tenant scope check for DeductionType
                $type = DeductionType::query()
                    ->where('id', $typeId)
                    ->where('is_active', true)
                    ->where(function ($q) use ($employee) {
                        $q->whereNull('company_id')
                          ->orWhere('company_id', $employee->company_id);
                    })
                    ->first();

                if (!$type) {
                    return response()->json([
                        'status' => 'error',
                        'message' => 'Unauthorized or invalid deduction type for this company.'
                    ], 422);
                }

                // If assignment ID provided, verify ownership and type match
                if ($assignmentId) {
                    $empDecRecord = EmpDeduction::where('id', $assignmentId)
                        ->where('employee_id', $employee->id)
                        ->first();
                    if (!$empDecRecord) {
                        return response()->json([
                            'status' => 'error',
                            'message' => 'Unauthorized or invalid deduction assignment ID.'
                        ], 422);
                    }
                    if ((int) $empDecRecord->deduction_type_id !== $typeId) {
                        return response()->json([
                            'status' => 'error',
                            'message' => 'Deduction assignment ID does not match the specified deduction type.'
                        ], 422);
                    }
                }

                // Deterministic deduplication per deduction_type_id
                $processedDeductions[$typeId] = [
                    'id' => $assignmentId,
                    'amount' => (float) ($decData['amount'] ?? 0),
                ];
            }
        }

        // Execute all updates inside an atomic DB transaction
        \Illuminate\Support\Facades\DB::transaction(function () use ($employee, $validatedData, $processedIncomes, $processedDeductions, $request) {
            $employee->update($validatedData);

            // Sync incomes non-destructively
            if (isset($request->incomes) && is_array($request->incomes)) {
                $keptIncomeIds = [];
                foreach ($processedIncomes as $typeId => $incItem) {
                    $assignmentId = $incItem['id'];
                    $amt = $incItem['amount'];

                    if ($assignmentId) {
                        $empInc = EmpIncome::where('id', $assignmentId)->where('employee_id', $employee->id)->first();
                        if ($empInc) {
                            $empInc->update([
                                'income_type_id' => $typeId,
                                'amount' => $amt,
                                'rate' => $amt,
                                'is_active' => true,
                            ]);
                            $keptIncomeIds[] = $empInc->id;
                        }
                    } else {
                        // Find existing assignment by type or create new
                        $empInc = EmpIncome::where('employee_id', $employee->id)
                            ->where('income_type_id', $typeId)
                            ->first();

                        if ($empInc) {
                            $empInc->update([
                                'amount' => $amt,
                                'rate' => $amt,
                                'is_active' => true,
                            ]);
                            $keptIncomeIds[] = $empInc->id;
                        } else {
                            $newInc = EmpIncome::create([
                                'employee_id' => $employee->id,
                                'income_type_id' => $typeId,
                                'amount' => $amt,
                                'rate' => $amt,
                                'is_active' => true,
                            ]);
                            $keptIncomeIds[] = $newInc->id;
                        }
                    }
                }

                // Delete ONLY assignments explicitly removed (not in kept list)
                EmpIncome::where('employee_id', $employee->id)
                    ->whereNotIn('id', $keptIncomeIds)
                    ->delete();
            }

            // Sync deductions non-destructively
            if (isset($request->deductions) && is_array($request->deductions)) {
                $keptDeductionIds = [];
                foreach ($processedDeductions as $typeId => $decItem) {
                    $assignmentId = $decItem['id'];
                    $amt = $decItem['amount'];

                    if ($assignmentId) {
                        $empDec = EmpDeduction::where('id', $assignmentId)->where('employee_id', $employee->id)->first();
                        if ($empDec) {
                            $empDec->update([
                                'deduction_type_id' => $typeId,
                                'amount' => $amt,
                                'rate' => $amt,
                                'is_active' => true,
                            ]);
                            $keptDeductionIds[] = $empDec->id;
                        }
                    } else {
                        // Find existing assignment by type or create new
                        $empDec = EmpDeduction::where('employee_id', $employee->id)
                            ->where('deduction_type_id', $typeId)
                            ->first();

                        if ($empDec) {
                            $empDec->update([
                                'amount' => $amt,
                                'rate' => $amt,
                                'is_active' => true,
                            ]);
                            $keptDeductionIds[] = $empDec->id;
                        } else {
                            $newDec = EmpDeduction::create([
                                'employee_id' => $employee->id,
                                'deduction_type_id' => $typeId,
                                'amount' => $amt,
                                'rate' => $amt,
                                'is_active' => true,
                            ]);
                            $keptDeductionIds[] = $newDec->id;
                        }
                    }
                }

                // Delete ONLY assignments explicitly removed (not in kept list)
                EmpDeduction::where('employee_id', $employee->id)
                    ->whereNotIn('id', $keptDeductionIds)
                    ->delete();
            }
        });

        return response()->json([
            'status' => 'success',
            'message' => 'Employee record saved successfully.',
            'employee' => $employee->fresh()
        ]);
    }
}
