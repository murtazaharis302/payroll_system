<?php

namespace App\Http\Controllers\Admin\Concerns;

use App\Models\EmpLoan;
use App\Models\LoanPayment;
use App\Models\MenuRoleAccess;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

trait LoanMonitorPaymentActions
{
    /**
     * AJAX: Payment History
     */
    public function paymentHistory($type, $id)
    {
        $companyId = $this->companyId();
        $loan = EmpLoan::with('employee')->find($id);

        if (!$loan) {
            return response()->json(['success' => false, 'message' => 'Loan record not found.'], 404);
        }
        if ($companyId && (int) $loan->employee->company_id !== (int) $companyId) {
            return response()->json(['success' => false, 'message' => 'Unauthorized access to loan record.'], 403);
        }

        $loanTotal = (float) ($loan->total_amount ?? $loan->principal_amount ?? 0.00);

        $paymentsAsc = LoanPayment::with(['payHeader.payrollPeriod'])
            ->where('emp_loan_id', $id)
            ->orderBy('payment_date', 'asc')
            ->orderBy('id', 'asc')
            ->get();

        $allPaymentsSum = (float) $paymentsAsc->sum('amount_paid');
        $runningBalance = $loanTotal;
        $mappedPayments = collect();

        foreach ($paymentsAsc as $p) {
            $paidAmt = (float) $p->amount_paid;
            $runningBalance = max(0.00, round($runningBalance - $paidAmt, 2));
            $isManual = strtoupper((string) ($p->source ?? '')) === 'MANUAL';
            $otherPayments = $allPaymentsSum - $paidAmt;
            $maxAllowed = max(0.00, round($loanTotal - $otherPayments, 2));

            $mappedPayments->push([
                'id'             => $p->id,
                'date'           => $p->payment_date ? $p->payment_date->format('M d, Y') : '—',
                'raw_date'       => $p->payment_date ? $p->payment_date->format('Y-m-d') : '',
                'payroll_period' => $p->payroll_period_name,
                'amount_paid'    => $paidAmt,
                'balance_after'  => $runningBalance,
                'notes'          => $p->notes,
                'source'         => strtoupper((string) ($p->source ?? '')),
                'is_manual'      => $isManual,
                'max_allowed'    => $maxAllowed,
            ]);
        }

        $sortedPayments = $mappedPayments->reverse()->values();

        return response()->json(['success' => true, 'payments' => $sortedPayments]);
    }

    /**
     * Record a Manual Payment
     */
    public function recordPayment(Request $request)
    {
        if (!MenuRoleAccess::hasModuleAccess(auth()->user(), 'loan_record_payment')) {
            abort(403, 'Unauthorized access to record loan payments.');
        }

        $request->validate([
            'emp_loan_id'  => 'required|exists:emp_loans,id',
            'amount_paid'  => 'required|numeric|min:0.01',
            'payment_date' => 'required|date',
            'pay_id'       => 'nullable|exists:pay_headers,id',
        ]);

        $companyId = $this->companyId();
        $errorResponse = null;

        DB::transaction(function () use ($request, $companyId, &$errorResponse) {
            $loan = EmpLoan::whereKey($request->emp_loan_id)->with('employee')->lockForUpdate()->firstOrFail();
            if ($companyId && (int) $loan->employee->company_id !== (int) $companyId) {
                abort(403, 'Unauthorized access to this loan record.');
            }

            if ($request->filled('pay_id')) {
                $alreadyPosted = LoanPayment::where('emp_loan_id', $loan->id)
                    ->where(fn($q) => $q->where('pay_id', $request->pay_id)->orWhere('pay_header_id', $request->pay_id))
                    ->exists();
                if ($alreadyPosted) {
                    $errorResponse = back()->with('statusDanger', 'Cannot record duplicate payment for an already posted payroll installment.');
                    return;
                }
            }

            if ((float) $loan->current_balance <= 0) {
                $errorResponse = back()->with('statusDanger', 'Loan is already fully paid.');
                return;
            }
            if ((float) $request->amount_paid > (float) $loan->current_balance) {
                $errorResponse = back()->with('statusDanger', 'Payment amount cannot exceed remaining balance.');
                return;
            }

            LoanPayment::create([
                'emp_loan_id'   => $loan->id,
                'pay_id'        => $request->input('pay_id'),
                'pay_header_id' => $request->input('pay_id'),
                'amount_paid'   => min((float) $request->amount_paid, (float) $loan->current_balance),
                'payment_date'  => $request->payment_date,
                'notes'         => 'Manual Payment',
                'source'        => 'MANUAL',
            ]);
            $loan->recalculateBalance();
        });

        if ($errorResponse) {
            return $errorResponse;
        }

        return back()->with('statusSuccess', 'Payment recorded successfully.');
    }

    /**
     * Update Loan Amount
     */
    public function updateLoan(Request $request, $id)
    {
        if (!MenuRoleAccess::hasModuleAccess(auth()->user(), 'loan_edit')) {
            abort(403, 'Unauthorized access to edit loan records.');
        }

        $request->validate(['principal_amount' => 'required|numeric|min:0.01']);
        $companyId = $this->companyId();

        DB::transaction(function () use ($request, $companyId, $id) {
            $loan = EmpLoan::whereKey($id)->with('employee')->lockForUpdate()->firstOrFail();
            if ($companyId && (int) $loan->employee->company_id !== (int) $companyId) {
                abort(403, 'Unauthorized access to this loan record.');
            }

            $newPrincipal = (float) $request->principal_amount;
            $termsMonths = (int) ($loan->terms_months ?: 1);
            $monthlyRate = (float) ($loan->monthly_interest_rate ?: 0.00);

            [$termsMonths, $interestAmount, $totalAmount, $perCutoffAmount] = $this->calculateLoanTerms($newPrincipal, $termsMonths, $monthlyRate);

            $totalAlreadyPaid = (float) $loan->payments()->sum('amount_paid');
            if ($totalAmount < $totalAlreadyPaid) {
                throw ValidationException::withMessages([
                    'principal_amount' => ["The proposed loan total (₱" . number_format($totalAmount, 2) . ") cannot be less than total already paid (₱" . number_format($totalAlreadyPaid, 2) . ")."],
                ]);
            }

            $loan->update([
                'principal_amount'  => $newPrincipal,
                'interest_amount'   => $interestAmount,
                'total_amount'      => $totalAmount,
                'per_cutoff_amount' => $perCutoffAmount,
            ]);
            $loan->recalculateBalance();
        });

        return back()->with('statusSuccess', 'Loan amount updated successfully.');
    }

    /**
     * Update Manual Payment
     */
    public function updatePayment(Request $request, $id)
    {
        if (!MenuRoleAccess::hasModuleAccess(auth()->user(), 'loan_payment_edit')) {
            abort(403, 'Unauthorized access to edit manual loan payments.');
        }

        $request->validate([
            'amount_paid'  => 'required|numeric|min:0.01',
            'payment_date' => 'required|date',
        ]);
        $companyId = $this->companyId();

        DB::transaction(function () use ($request, $companyId, $id) {
            $payment = LoanPayment::whereKey($id)->with('empLoan.employee')->lockForUpdate()->firstOrFail();
            $loan = EmpLoan::whereKey($payment->emp_loan_id)->lockForUpdate()->firstOrFail();

            if ($companyId && (int) $payment->empLoan->employee->company_id !== (int) $companyId) {
                abort(403, 'Unauthorized access to this loan payment record.');
            }
            if (strtoupper((string) ($payment->source ?? '')) !== 'MANUAL') {
                abort(403, 'Payroll-generated loan payments cannot be edited from Loan Monitor.');
            }

            $newAmount = (float) $request->amount_paid;
            $otherPayments = (float) LoanPayment::where('emp_loan_id', $loan->id)->where('id', '!=', $payment->id)->sum('amount_paid');
            $loanTotal = (float) ($loan->total_amount ?? $loan->principal_amount ?? 0.00);
            $maxAllowed = max(0.00, round($loanTotal - $otherPayments, 2));

            if (round($newAmount + $otherPayments, 2) > round($loanTotal, 2)) {
                throw ValidationException::withMessages([
                    'amount_paid' => ["The payment amount cannot exceed ₱" . number_format($maxAllowed, 2) . " (maximum allowable payment for this loan)."],
                ]);
            }

            $payment->update([
                'amount_paid'  => $newAmount,
                'payment_date' => $request->payment_date,
            ]);
            $loan->recalculateBalance();
        });

        return back()->with('statusSuccess', 'Manual payment updated successfully.');
    }
}
