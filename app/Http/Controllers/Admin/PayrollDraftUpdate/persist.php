<?php

use App\Models\ActivityLog;
use App\Models\EmpLoan;
use App\Models\PayHeader;
use App\Models\PayIn;
use App\Models\PayOut;
use App\Services\PayrollProcessingService;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Schema;

            // If pay_ins array is provided in request (Master Quick-Entry format)
            if ($request->has('pay_ins') && is_array($request->input('pay_ins'))) {
                $isDailyEmp = ($emp && (float)$emp->daily_rate > 0 && (float)$emp->monthly_rate <= 0);

                foreach ($request->input('pay_ins') as $incData) {
                    if (isset($incData['id'])) {
                        $payIn = \App\Models\PayIn::withoutGlobalScopes()
                            ->with('incomeType')
                            ->where('id', $incData['id'])
                            ->where('pay_id', $draft->id)
                            ->first();

                        if (!$payIn) {
                            DB::rollBack();
                            if ($request->wantsJson()) {
                                return response()->json(['status' => 'error', 'message' => 'Submitted PayIn line item does not belong to this payroll draft.'], 422);
                            }
                            return redirect()->back()
                                ->withInput()
                                ->with('statusDanger', 'Submitted PayIn line item does not belong to this payroll draft.');
                        }

                        if (array_key_exists('rate', $incData) && $incData['rate'] !== null) {
                            $rateVal = (float) $incData['rate'];
                            if ($rateVal < 0) {
                                DB::rollBack();
                                if ($request->wantsJson()) {
                                    return response()->json(['status' => 'error', 'message' => 'Income rate cannot be negative.'], 422);
                                }
                                return redirect()->back()
                                    ->withInput()
                                    ->with('statusDanger', 'Income rate cannot be negative.');
                            }
                            $payIn->rate = $rateVal;
                        }

                        if (array_key_exists('number_input', $incData) && $incData['number_input'] !== null) {
                            $numVal = (float) $incData['number_input'];
                            if ($numVal < 0) {
                                DB::rollBack();
                                if ($request->wantsJson()) {
                                    return response()->json(['status' => 'error', 'message' => 'Number input cannot be negative.'], 422);
                                }
                                return redirect()->back()
                                    ->withInput()
                                    ->with('statusDanger', 'Number input cannot be negative.');
                            }

                            $payIn->number_input = $numVal;
                        }

                        $payIn->save();
                    }
                }
            }

            // Record pre-recalculation rates to detect user overrides vs untouched form fields
            $preRecalcRates = [];
            foreach ($draft->payOuts as $po) {
                $preRecalcRates[$po->id] = (float) $po->rate;
            }

            // Recalculate income-dependent statutory deductions (e.g. Withholding Tax) from current draft income totals
            $processingService = app(PayrollProcessingService::class);
            $processingService->recalculateDraftStatutoryDeductions($draft);

            // If pay_outs array is provided in request
            if ($request->has('pay_outs') && is_array($request->input('pay_outs'))) {
                foreach ($request->input('pay_outs') as $decData) {
                    if (isset($decData['id'])) {
                        $payOut = \App\Models\PayOut::withoutGlobalScopes()
                            ->where('id', $decData['id'])
                            ->where('pay_id', $draft->id)
                            ->first();

                        if (!$payOut) {
                            DB::rollBack();
                            if ($request->wantsJson()) {
                                return response()->json(['status' => 'error', 'message' => 'Submitted PayOut line item does not belong to this payroll draft.'], 422);
                            }
                            return redirect()->back()
                                ->withInput()
                                ->with('statusDanger', 'Submitted PayOut line item does not belong to this payroll draft.');
                        }

                        if (array_key_exists('rate', $decData) && $decData['rate'] !== null) {
                            $rateVal = (float) $decData['rate'];
                            if ($rateVal < 0) {
                                DB::rollBack();
                                if ($request->wantsJson()) {
                                    return response()->json(['status' => 'error', 'message' => 'Deduction input cannot be negative.'], 422);
                                }
                                return redirect()->back()->withInput()->with('statusDanger', 'Deduction input cannot be negative.');
                            }

                            if (!empty($payOut->loan_id)) {
                                $loan = EmpLoan::withoutGlobalScopes()->find($payOut->loan_id);
                                if ($loan && $rateVal > (float) $loan->current_balance) {
                                    DB::rollBack();
                                    $msg = "Loan deduction amount (₱" . number_format($rateVal, 2) . ") cannot exceed the current loan balance (₱" . number_format((float)$loan->current_balance, 2) . ").";
                                    if ($request->wantsJson()) {
                                        return response()->json(['status' => 'error', 'message' => $msg], 422);
                                    }
                                    return redirect()->back()->withInput()->with('statusDanger', $msg);
                                }
                            }

                            $isStatutory = in_array($payOut->deductionType?->system_key, ['sss', 'philhealth', 'pagibig', 'tax'], true);
                            $wasUntouched = $isStatutory && isset($preRecalcRates[$payOut->id]) && abs($preRecalcRates[$payOut->id] - $rateVal) < 0.001;

                            if (!$wasUntouched) {
                                $payOut->rate = $rateVal;
                            }
                        }

                        if (array_key_exists('number_input', $decData) && $decData['number_input'] !== null) {
                            $numVal = (float) $decData['number_input'];
                            if ($numVal < 0) {
                                DB::rollBack();
                                if ($request->wantsJson()) {
                                    return response()->json(['status' => 'error', 'message' => 'Number input cannot be negative.'], 422);
                                }
                                return redirect()->back()->withInput()->with('statusDanger', 'Number input cannot be negative.');
                            }
                            $payOut->number_input = $numVal;
                        }

                        if (strtoupper((string) ($payOut->deductionType?->code ?? '')) === 'ABSENCES') {
                            $payOut->number_input = 1.00;
                        }

                        $payOut->save();
                    }
                }
            }

            // Sync linked GovernmentContribution records with updated salary basis and PayOuts
            $processingService->syncDraftGovernmentContributions($draft);

            // Calculate totals from PayIn and PayOut tables
            $totals = $draft->recalculateTotals();
            $netPay = $totals['net_pay'];

            $isSubmitting = $request->input('submit_after_save') == '1';
            $empCode = $draft->employee->employee_code ?? 'EMP-' . $draft->employee_id;
            $empName = $draft->employee->full_name ?? 'Employee';
            $isHrUser = $currentUser->role === 'hr' || $currentUser->role_id === \App\Models\Role::HR;

            if ($isSubmitting) {
                if (!$draft->canSubmit()) {
                    DB::rollBack();
                    $msg = "Cannot submit payroll draft. Draft is currently in status '{$draft->status}' and cannot be submitted.";
                    if ($request->wantsJson()) {
                        return response()->json(['status' => 'error', 'message' => $msg], 422);
                    }
                    return back()->withInput()->with('statusDanger', $msg);
                }

                if ($draft->requires_review && empty($validated['override_reason'])) {
                    DB::rollBack();
                    $msg = "Cannot submit payroll draft. Employee {$empCode} - {$empName} requires review: " . ($draft->review_reason ?? 'Requires review before submission.') . " Please provide an override reason before submitting.";
                    if ($request->wantsJson()) {
                        return response()->json(['status' => 'error', 'message' => $msg], 422);
                    }
                    return back()->withInput()->with('statusDanger', $msg);
                }

                if ($netPay < 0) {
                    DB::rollBack();
                    $msg = $isHrUser
                        ? "Cannot submit payroll draft. Employee {$empCode} - {$empName} has a negative net income. Please adjust attendance or deductions before submitting."
                        : "Cannot submit payroll draft. Employee {$empCode} - {$empName} has a negative net income of ₱" . number_format($netPay, 2) . ". Please adjust income or deductions before submitting.";
                    if ($request->wantsJson()) {
                        return response()->json(['status' => 'error', 'message' => $msg], 422);
                    }
                    return back()->withInput()->with('statusDanger', $msg);
                }
            }

            $updateData = $totals;

            if (Schema::hasColumn('pay_headers', 'days_worked')) {
                $updateData['days_worked'] = $daysWorked;
                $updateData['days_absent'] = (float) ($validated['days_absent'] ?? $draft->days_absent ?? 0.00);
                $updateData['overtime_hours'] = $otHours;
                $updateData['late_hours'] = (float) ($validated['late_hours'] ?? $draft->late_hours ?? 0.00);
            }

            $updateData['requires_review'] = (bool) $draft->requires_review;
            $updateData['review_reason'] = $draft->review_reason;

            if (!empty($validated['override_reason'])) {
                $updateData['requires_review'] = false;
                $updateData['review_reason'] = null;
                $updateData['attendance_override_reason'] = $validated['override_reason'];
                $updateData['attendance_override_by'] = Auth::id();
                $updateData['attendance_override_at'] = now();

                ActivityLog::log(
                    'override_attendance',
                    'payroll_draft',
                    "Authorized attendance override for draft ID {$draft->id}: " . $validated['override_reason']
                );
            }

            $draft->update($updateData);

            if ($isSubmitting) {
                $workflowService = app(\App\Services\PayrollWorkflowService::class);
                $workflowService->transition($draft, \App\Services\PayrollWorkflowService::STATE_PENDING_APPROVAL, ['user' => $currentUser]);
            }

            DB::commit();

            if ($isSubmitting) {
                ActivityLog::log(
                    'submit',
                    'payroll_draft',
                    "Submitted payroll draft for approval",
                    PayHeader::class,
                    $draft->id
                );

                if ($request->wantsJson()) {
                    return response()->json([
                        'status' => 'success',
                        'message' => 'Payroll draft saved and submitted for approval successfully.',
                        'draft_id' => $draft->id,
                        'payroll_period_id' => $draft->payroll_period_id,
                    ]);
                }

                return redirect()->route('payroll-drafts.index', ['payroll_period_id' => $draft->payroll_period_id])
                    ->with('status', 'Payroll draft saved and submitted for approval successfully.');
            }

            if ($request->wantsJson()) {
                return response()->json([
                    'status' => 'success',
                    'message' => 'Payroll draft saved successfully.',
                    'draft_id' => $draft->id,
                    'payroll_period_id' => $draft->payroll_period_id,
                ]);
            }

            ActivityLog::log(
                'update',
                'payroll_draft',
                "Updated payroll draft for employee",
                PayHeader::class,
                $draft->id
            );

            return redirect()->route('payroll-drafts.edit', [
                'id' => $draft->id,
                'payroll_period_id' => $draft->payroll_period_id,
            ])->with('status', 'Payroll draft saved successfully.');
