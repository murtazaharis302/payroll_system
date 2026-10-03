{{-- payslip-card.blade.php — compact payslip for batch PDF (4 per A4 landscape page) --}}
@php
    $payHeader = $payroll;
    
    // Count active (non-zero) income and deduction items
    $incomeCount = $payHeader->payIns->where('amount', '>', 0)->count();
    $deductionCount = $payHeader->payOuts->where('amount', '>', 0)->count();
    $maxItems = max($incomeCount, $deductionCount);

    // Density selection:
    // 0–10 items: Normal baseline (no dynamic CSS overrides)
    // 11–12 items: Compact
    // 13+ items: Dense
    $isDense = $maxItems >= 13;
    $isCompact = $maxItems >= 11 && !$isDense;
    
    $uniqueId = $payroll->id . '_' . uniqid();
@endphp

@if($isDense || $isCompact)
@php
    $fontSize = $isDense ? '6.5px' : '7.5px';
    $lineHeight = $isDense ? '1.15' : '1.25';
    $margin = $isDense ? '2.5px' : '3.5px';
    $headerSize = $isDense ? '8.5px' : '9.5px';
    $rowPadding = $isDense ? '0.6px' : '1.2px';
@endphp
<style>
    .ps-card-{{ $uniqueId }} .ps-header {
        margin-bottom: {{ $margin }} !important;
        padding-bottom: {{ $margin }} !important;
    }
    .ps-card-{{ $uniqueId }} .ps-company {
        font-size: {{ $headerSize }} !important;
    }
    .ps-card-{{ $uniqueId }} .ps-title {
        font-size: {{ $headerSize }} !important;
        margin-top: {{ $margin }} !important;
    }
    .ps-card-{{ $uniqueId }} .ps-emp {
        font-size: {{ $fontSize }} !important;
        margin-bottom: {{ $margin }} !important;
    }
    .ps-card-{{ $uniqueId }} .ps-emp-row div {
        padding: {{ $rowPadding }} 0 !important;
    }
    .ps-card-{{ $uniqueId }} .ps-body {
        padding-top: {{ $margin }} !important;
        margin-bottom: {{ $margin }} !important;
    }
    .ps-card-{{ $uniqueId }} .col-title {
        font-size: {{ $fontSize }} !important;
        margin-bottom: {{ $margin }} !important;
    }
    .ps-card-{{ $uniqueId }} .ps-line {
        padding: {{ $rowPadding }} 0 !important;
        line-height: {{ $lineHeight }} !important;
    }
    .ps-card-{{ $uniqueId }} .ps-line-label, 
    .ps-card-{{ $uniqueId }} .ps-line-value {
        font-size: {{ $fontSize }} !important;
    }
    .ps-card-{{ $uniqueId }} .subtotal-line {
        margin-top: {{ $margin }} !important;
        padding-top: {{ $margin }} !important;
    }
    .ps-card-{{ $uniqueId }} .subtotal-lbl,
    .ps-card-{{ $uniqueId }} .subtotal-val {
        font-size: {{ $fontSize }} !important;
    }
    .ps-card-{{ $uniqueId }} .ps-netpay {
        margin-top: {{ $margin }} !important;
        padding-top: {{ $margin }} !important;
    }
    .ps-card-{{ $uniqueId }} .netpay-lbl {
        font-size: calc({{ $fontSize }} + 1.5px) !important;
    }
    .ps-card-{{ $uniqueId }} .netpay-val {
        font-size: calc({{ $fontSize }} + 3px) !important;
    }
    .ps-card-{{ $uniqueId }} .ps-footer {
        margin-top: {{ $margin }} !important;
        padding-top: {{ $margin }} !important;
        font-size: calc({{ $fontSize }} - 1px) !important;
    }
</style>
@endif

<div class="payslip ps-card-{{ $uniqueId }}">

    {{-- ===== HEADER ===== --}}
    <div class="ps-header">
        <div class="ps-company">{{ $company->company_name ?? 'Company Name' }}</div>
        @if(!empty($company->company_address))
        <div class="ps-company-sub">{{ $company->company_address }}</div>
        @endif
        <div class="ps-title">PAYSLIP</div>
    </div>

    {{-- ===== EMPLOYEE INFO ===== --}}
    <div class="ps-emp">
        <div class="ps-emp-row">
            <div class="ps-emp-l">
                <span class="emp-lbl">Name: </span>
                <span class="emp-val">{{ $payHeader->employee->first_name }} {{ $payHeader->employee->last_name }}</span>
            </div>
            <div class="ps-emp-r">
                <span class="emp-lbl">Emp #: </span>
                <span class="emp-val">{{ $payHeader->employee->employee_code ?? 'N/A' }}</span>
            </div>
        </div>
        <div class="ps-emp-row">
            <div class="ps-emp-l">
                <span class="emp-lbl">Position: </span>
                <span class="emp-val">{{ $payHeader->employee->position ?? 'N/A' }}</span>
            </div>
            <div class="ps-emp-r">
                <span class="emp-lbl">Period: </span>
                <span class="emp-val">{{ $period->period_name }}</span>
            </div>
        </div>
        <div class="ps-emp-row">
            <div class="ps-emp-l">
                <span class="emp-lbl">From: </span>
                <span class="emp-val">{{ \Carbon\Carbon::parse($period->period_start)->format('M d, Y') }}</span>
            </div>
            <div class="ps-emp-r">
                <span class="emp-lbl">To: </span>
                <span class="emp-val">{{ \Carbon\Carbon::parse($period->period_end)->format('M d, Y') }}</span>
            </div>
        </div>
    </div>

    {{-- ===== EARNINGS + DEDUCTIONS SIDE BY SIDE ===== --}}
    <div class="ps-body">

        {{-- Left: Earnings --}}
        <div class="ps-col">
            <div class="col-title">Earnings</div>

            @foreach($payHeader->payIns as $income)
                @if($income->amount > 0)
                <div class="ps-line">
                    <span class="ps-line-label">
                        {{ $income->incomeType->name }}
                        @if($income->incomeType->code === 'OVERTIME' || $income->incomeType->code === 'OT')
                            ({{ number_format($income->number_input, 1) }}h)
                        @endif
                    </span>
                    <span class="ps-line-value">{{ number_format($income->amount, 2) }}</span>
                </div>
                @endif
            @endforeach

            <div class="subtotal-line">
                <span class="subtotal-lbl">Gross Pay</span>
                <span class="subtotal-val">{{ number_format($payHeader->total_gross_pay, 2) }}</span>
            </div>
        </div>

        {{-- Right: Deductions --}}
        <div class="ps-col">
            <div class="col-title">Deductions</div>

            @foreach($payHeader->payOuts as $deduction)
                @if($deduction->amount > 0)
                <div class="ps-line">
                    <span class="ps-line-label">
                        @if($deduction->loan)
                            {{ $deduction->loan->loanType->name ?? $deduction->deductionType->name }} (Loan)
                        @else
                            {{ $deduction->deductionType->name }}
                        @endif
                        @if($deduction->deductionType->code === 'TARDINESS' || $deduction->deductionType->code === 'UNDERTIME')
                            ({{ number_format($deduction->number_input, 1) }}h)
                        @endif
                    </span>
                    <span class="ps-line-value">{{ number_format($deduction->amount, 2) }}</span>
                </div>
                @endif
            @endforeach

            <div class="subtotal-line">
                <span class="subtotal-lbl">Total Ded.</span>
                <span class="subtotal-val">{{ number_format($payHeader->total_deductions, 2) }}</span>
            </div>
        </div>

    </div>

    {{-- ===== ===== NET PAY ===== ===== --}}
    <div class="ps-netpay">
        <span class="netpay-lbl">Net Pay:</span>
        <span class="netpay-val">PHP {{ number_format($payHeader->net_pay, 2) }}</span>
    </div>

    {{-- ===== FOOTER ===== --}}
    <div class="ps-footer">
        Computer-generated payslip &bull; {{ \Carbon\Carbon::now()->format('M d, Y') }}
    </div>

</div>
