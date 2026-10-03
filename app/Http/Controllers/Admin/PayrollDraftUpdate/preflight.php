<?php

use App\Models\ActivityLog;
use App\Models\MenuRoleAccess;
use App\Models\PayHeader;
use App\Models\PayrollPeriod;
use Illuminate\Support\Facades\Auth;

        $currentUser = auth()->user();
        $draft = PayHeader::withoutGlobalScopes()->findOrFail($id);

        $companyId = $this->getCompanyId();
        if ($companyId && $draft->employee && (int) $draft->employee->company_id !== (int) $companyId) {
            abort(403, 'Unauthorized access to this payroll draft.');
        }
        if ($companyId && $draft->company_id && (int) $draft->company_id !== (int) $companyId) {
            abort(403, 'Unauthorized access to this payroll draft.');
        }


        if (!$draft->canBeEditedBy($currentUser)) {
            if ($request->wantsJson()) {
                return response()->json(['status' => 'error', 'message' => 'This payroll record is locked and cannot be edited.'], 422);
            }
            return redirect()->back()
                ->withInput()
                ->with('statusDanger', 'This payroll record is locked and cannot be edited.');
        }

        $hasIncomeInputPermission = MenuRoleAccess::hasModuleAccess($currentUser, 'payroll_income_inputs');
        $hasRateVisibilityPermission = MenuRoleAccess::hasModuleAccess($currentUser, 'payroll_income_rate_visibility');
        $hasDeductionInputPermission = MenuRoleAccess::hasModuleAccess($currentUser, 'payroll_deduction_inputs');

        if (!$hasRateVisibilityPermission) {
            $forbiddenRateFields = ['basic_pay', 'overtime_pay', 'total_allowances', 'total_bonuses', 'other_income', 'custom_incomes'];
            foreach ($forbiddenRateFields as $field) {
                if ($request->has($field) && $request->input($field) !== null) {
                    abort(403, 'Users without rate visibility are restricted from submitting or modifying salary rate amounts.');
                }
            }

            if ($request->has('pay_ins') && is_array($request->input('pay_ins'))) {
                foreach ($request->input('pay_ins') as $incItem) {
                    if (is_array($incItem) && (array_key_exists('rate', $incItem) || array_key_exists('amount', $incItem))) {
                        abort(403, 'Users without rate visibility are restricted from submitting or modifying salary rate amounts.');
                    }
                }
            }
        }

        if (!$hasIncomeInputPermission && $request->has('pay_ins') && $request->input('pay_ins') !== null) {
            abort(403, 'User is restricted from submitting or modifying payroll income inputs without authorization.');
        }

        if (!$hasDeductionInputPermission) {
            if ($request->has('pay_outs') && $request->input('pay_outs') !== null) {
                abort(403, 'User is restricted from submitting or modifying payroll deduction inputs without authorization.');
            }

            $forbiddenDeductionFields = [
                'sss_contribution', 'philhealth_contribution', 'pagibig_contribution',
                'withholding_tax', 'sss_loan_deduction', 'pagibig_loan_deduction',
                'vale_loan_deduction', 'goods_purchase_deduction', 'late_deduction',
                'undertime_deduction', 'other_deductions', 'custom_deductions',
            ];
            foreach ($forbiddenDeductionFields as $field) {
                if ($request->has($field) && $request->input($field) !== null) {
                    abort(403, 'User is restricted from submitting or modifying payroll deduction inputs without authorization.');
                }
            }
        }
        $validated = $request->validate([

            'days_worked' => 'required|numeric|min:0|max:31',
            'days_absent' => 'nullable|numeric|min:0',
            'overtime_hours' => 'nullable|numeric|min:0',
            'late_hours' => 'nullable|numeric|min:0',
            'undertime_hours' => 'nullable|numeric|min:0',
            'basic_pay' => 'nullable|numeric|min:0',
            'overtime_pay' => 'nullable|numeric|min:0',
            'total_allowances' => 'nullable|numeric|min:0',
            'total_bonuses' => 'nullable|numeric|min:0',
            'other_income' => 'nullable|numeric|min:0',
            'sss_contribution' => 'nullable|numeric|min:0',
            'philhealth_contribution' => 'nullable|numeric|min:0',
            'pagibig_contribution' => 'nullable|numeric|min:0',
            'withholding_tax' => 'nullable|numeric|min:0',
            'sss_loan_deduction' => 'nullable|numeric|min:0',
            'pagibig_loan_deduction' => 'nullable|numeric|min:0',
            'vale_loan_deduction' => 'nullable|numeric|min:0',
            'goods_purchase_deduction' => 'nullable|numeric|min:0',
            'late_deduction' => 'nullable|numeric|min:0',
            'undertime_deduction' => 'nullable|numeric|min:0',
            'other_deductions' => 'nullable|numeric|min:0',
            'custom_incomes' => 'nullable|array',
            'custom_incomes.*' => 'nullable|numeric|min:0',
            'custom_deductions' => 'nullable|array',
            'custom_deductions.*' => 'nullable|numeric|min:0',
            'notes' => 'nullable|string',
            'override_reason' => 'nullable|string|max:500',
        ]);

        // 1. Reject type ID keys entirely in Payroll Input
        if ($request->has('income_type_id') || $request->has('deduction_type_id')) {
            if ($request->wantsJson()) {
                return response()->json(['status' => 'error', 'message' => 'Submitting income_type_id or deduction_type_id in payroll input is not allowed.'], 422);
            }
            return redirect()->back()->withInput()->with('statusDanger', 'Submitting income_type_id or deduction_type_id in payroll input is not allowed.');
        }

        if ($request->has('pay_ins') && is_array($request->input('pay_ins'))) {
            foreach ($request->input('pay_ins') as $incItem) {
                if (is_array($incItem) && (array_key_exists('income_type_id', $incItem) || array_key_exists('deduction_type_id', $incItem))) {
                    if ($request->wantsJson()) {
                        return response()->json(['status' => 'error', 'message' => 'Submitting income_type_id or deduction_type_id in payroll input is not allowed.'], 422);
                    }
                    return redirect()->back()->withInput()->with('statusDanger', 'Submitting income_type_id or deduction_type_id in payroll input is not allowed.');
                }
            }
        }

        if ($request->has('pay_outs') && is_array($request->input('pay_outs'))) {
            foreach ($request->input('pay_outs') as $decItem) {
                if (is_array($decItem) && (array_key_exists('income_type_id', $decItem) || array_key_exists('deduction_type_id', $decItem))) {
                    if ($request->wantsJson()) {
                        return response()->json(['status' => 'error', 'message' => 'Submitting income_type_id or deduction_type_id in payroll input is not allowed.'], 422);
                    }
                    return redirect()->back()->withInput()->with('statusDanger', 'Submitting income_type_id or deduction_type_id in payroll input is not allowed.');
                }
            }
        }

        // 2. Pre-validate PayIn line IDs and ownership
        if ($request->has('pay_ins')) {
            if (!is_array($request->input('pay_ins'))) {
                if ($request->wantsJson()) {
                    return response()->json(['status' => 'error', 'message' => 'Invalid pay_ins payload format.'], 422);
                }
                return redirect()->back()->withInput()->with('statusDanger', 'Invalid pay_ins payload format.');
            }

            foreach ($request->input('pay_ins') as $incData) {
                if (!is_array($incData) || empty($incData['id'])) {
                    if ($request->wantsJson()) {
                        return response()->json(['status' => 'error', 'message' => 'Every submitted income line item must have an existing line ID.'], 422);
                    }
                    return redirect()->back()->withInput()->with('statusDanger', 'Every submitted income line item must have an existing line ID.');
                }

                $payIn = \App\Models\PayIn::withoutGlobalScopes()->find($incData['id']);
                if (!$payIn) {
                    if ($request->wantsJson()) {
                        return response()->json(['status' => 'error', 'message' => 'Submitted income line ID does not exist.'], 422);
                    }
                    return redirect()->back()->withInput()->with('statusDanger', 'Submitted income line ID does not exist.');
                }

                if ((int) $payIn->pay_id !== (int) $draft->id) {
                    if ($request->wantsJson()) {
                        return response()->json(['status' => 'error', 'message' => 'Submitted PayIn line item does not belong to this payroll draft.'], 422);
                    }
                    return redirect()->back()->withInput()->with('statusDanger', 'Submitted PayIn line item does not belong to this payroll draft.');
                }

                $payInHeader = PayHeader::withoutGlobalScopes()->find($payIn->pay_id);
                if (!$payInHeader || (int) $payInHeader->employee_id !== (int) $draft->employee_id || (int) $payInHeader->company_id !== (int) $draft->company_id) {
                    if ($request->wantsJson()) {
                        return response()->json(['status' => 'error', 'message' => 'Submitted line item belongs to another employee or company.'], 422);
                    }
                    return redirect()->back()->withInput()->with('statusDanger', 'Submitted line item belongs to another employee or company.');
                }
            }
        }

        // 3. Pre-validate PayOut line IDs and ownership
        if ($request->has('pay_outs')) {
            if (!is_array($request->input('pay_outs'))) {
                if ($request->wantsJson()) {
                    return response()->json(['status' => 'error', 'message' => 'Invalid pay_outs payload format.'], 422);
                }
                return redirect()->back()->withInput()->with('statusDanger', 'Invalid pay_outs payload format.');
            }

            foreach ($request->input('pay_outs') as $decData) {
                if (!is_array($decData) || empty($decData['id'])) {
                    if ($request->wantsJson()) {
                        return response()->json(['status' => 'error', 'message' => 'Every submitted deduction line item must have an existing line ID.'], 422);
                    }
                    return redirect()->back()->withInput()->with('statusDanger', 'Every submitted deduction line item must have an existing line ID.');
                }

                $payOut = \App\Models\PayOut::withoutGlobalScopes()->find($decData['id']);
                if (!$payOut) {
                    if ($request->wantsJson()) {
                        return response()->json(['status' => 'error', 'message' => 'Submitted deduction line ID does not exist.'], 422);
                    }
                    return redirect()->back()->withInput()->with('statusDanger', 'Submitted deduction line ID does not exist.');
                }

                if ((int) $payOut->pay_id !== (int) $draft->id) {
                    if ($request->wantsJson()) {
                        return response()->json(['status' => 'error', 'message' => 'Submitted PayOut line item does not belong to this payroll draft.'], 422);
                    }
                    return redirect()->back()->withInput()->with('statusDanger', 'Submitted PayOut line item does not belong to this payroll draft.');
                }

                $payOutHeader = PayHeader::withoutGlobalScopes()->find($payOut->pay_id);
                if (!$payOutHeader || (int) $payOutHeader->employee_id !== (int) $draft->employee_id || (int) $payOutHeader->company_id !== (int) $draft->company_id) {
                    if ($request->wantsJson()) {
                        return response()->json(['status' => 'error', 'message' => 'Submitted line item belongs to another employee or company.'], 422);
                    }
                    return redirect()->back()->withInput()->with('statusDanger', 'Submitted line item belongs to another employee or company.');
                }
            }
        }

        return null;
