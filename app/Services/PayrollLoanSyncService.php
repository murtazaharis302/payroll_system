<?php

declare(strict_types=1);

namespace App\Services;

use App\Models\EmpLoan;
use App\Models\LoanPayment;
use App\Models\PayHeader;

class PayrollLoanSyncService
{
    /**
     * Synchronize payroll loan deductions (PayOut lines) to LoanPayment records.
     *
     * @param PayHeader $payHeader
     * @return void
     */
    public function sync(PayHeader $payHeader): void
    {
        // 1. Get existing PAYROLL LoanPayment records linked to this PayHeader
        $existingPayrollPayments = LoanPayment::where('source', 'PAYROLL')
            ->where(function ($q) use ($payHeader) {
                $q->where('pay_header_id', $payHeader->id)
                  ->orWhere('pay_id', $payHeader->id);
            })
            ->get();

        // 2. Get loan PayOut lines from this PayHeader
        $loanPayOuts = $payHeader->payOuts()
            ->where(function ($q) {
                $q->whereNotNull('loan_id')
                  ->orWhereHas('deductionType', function ($dq) {
                      $dq->where('is_loan_enabled', true);
                  });
            })
            ->get();

        $processedPaymentIds = [];
        $affectedLoans = [];

        foreach ($loanPayOuts as $payOut) {
            $deductionAmount = (float) ($payOut->amount ?? (($payOut->rate ?? 0) * ($payOut->number_input ?? 1)));

            // Locate target EmpLoan (constrained to the payroll employee)
            $activeLoan = null;
            if ($payOut->loan_id) {
                $activeLoan = EmpLoan::where('id', $payOut->loan_id)
                    ->where('employee_id', $payHeader->employee_id)
                    ->lockForUpdate()
                    ->first();
            }

            if (!$activeLoan && $payOut->deduction_type_id) {
                // Check if an existing PAYROLL payment for this header is already linked to a loan of this deduction type
                $existingP = $existingPayrollPayments->first(function ($p) use ($payOut) {
                    return $p->empLoan?->loanType?->deduction_type_id == $payOut->deduction_type_id;
                });

                if ($existingP && $existingP->empLoan) {
                    $activeLoan = $existingP->empLoan;
                } else {
                    $activeLoan = EmpLoan::where('employee_id', $payHeader->employee_id)
                        ->where('status', 'ACTIVE')
                        ->where('current_balance', '>', 0)
                        ->whereHas('loanType', function ($lq) use ($payOut) {
                            $lq->where('deduction_type_id', $payOut->deduction_type_id);
                        })
                        ->orderBy('approved_at', 'asc')
                        ->orderBy('id', 'asc')
                        ->lockForUpdate()
                        ->first();
                }
            }

            if (!$activeLoan) {
                continue;
            }

            // Find existing PAYROLL LoanPayment for this loan and payHeader
            $payment = LoanPayment::where('emp_loan_id', $activeLoan->id)
                ->where('source', 'PAYROLL')
                ->where(function ($q) use ($payHeader) {
                    $q->where('pay_header_id', $payHeader->id)
                      ->orWhere('pay_id', $payHeader->id);
                })
                ->first();

            $paymentDate = $payHeader->payrollPeriod?->pay_date ?? now()->toDateString();
            $periodName = $payHeader->payrollPeriod?->period_name ?? 'Payroll';
            $notes = 'Payroll deduction (' . $periodName . ')';

            if ($deductionAmount > 0) {
                $applicableBalance = (float) $activeLoan->current_balance + ($payment ? (float) $payment->amount_paid : 0.0);
                $payableAmount = min($deductionAmount, $applicableBalance);

                if ($payableAmount > 0) {
                    if ($payment) {
                        // Update existing payment in place
                        $payment->amount_paid = $payableAmount;
                        $payment->payment_date = $paymentDate;
                        $payment->notes = $notes;
                        $payment->save();
                    } else {
                        // Create new payment
                        $payment = LoanPayment::create([
                            'emp_loan_id'   => $activeLoan->id,
                            'pay_header_id' => $payHeader->id,
                            'pay_id'        => $payHeader->id,
                            'amount_paid'   => $payableAmount,
                            'payment_date'  => $paymentDate,
                            'notes'         => $notes,
                            'source'        => 'PAYROLL',
                        ]);
                    }

                    $processedPaymentIds[] = $payment->id;
                    $affectedLoans[$activeLoan->id] = $activeLoan;
                }
            } else {
                // If deduction amount is <= 0, remove corresponding PAYROLL payment
                if ($payment) {
                    $payment->delete();
                    $affectedLoans[$activeLoan->id] = $activeLoan;
                }
            }
        }

        // 3. Clean up any remaining PAYROLL payments for this payHeader whose PayOut line was removed
        foreach ($existingPayrollPayments as $existingPayment) {
            if (!in_array($existingPayment->id, $processedPaymentIds, true)) {
                $loan = $existingPayment->empLoan;
                $existingPayment->delete();
                if ($loan) {
                    $affectedLoans[$loan->id] = $loan;
                }
            }
        }

        // 4. Recalculate balance once for each affected loan
        foreach ($affectedLoans as $loan) {
            $loan->recalculateBalance();
        }
    }
}
