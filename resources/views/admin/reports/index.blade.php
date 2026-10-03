@extends('admin.layouts.Master')

@section('title', 'Reports Hub & Statutory Compliance')

@section('content')
    @include('admin.reports._hub_header')

    @php
        $latestPeriod = isset($payrollPeriods) ? $payrollPeriods->first() : null;
        $stdDeductionNames = ['sss', 'sss contribution', 'philhealth', 'philhealth contribution', 'pagibig', 'pag-ibig', 'pagibig contribution', 'pag-ibig contribution', 'hdmf', 'withholding tax', 'w/tax', 'tax', 'sss loan', 'pagibig loan', 'pag-ibig loan', 'vale', 'vale deduction', 'vale loan', 'goods', 'goods deduction', 'goods purchase', 'goods purchase deduction', 'absences', 'absences deduction', 'company loan', 'company loan deduction', 'hdmf loan'];
        $stdIncomeNames = ['basic salary', 'basic pay', 'basic', 'salary', 'overtime', 'overtime pay', 'ot', 'night diff', 'night diff pay', 'holiday pay', 'holiday', '13th month pay', '13th month', 'allowance', 'allowances', 'incentive', 'incentives', 'other income', 'bonuses', 'bonus', 'basic pay2', 'basic pay 2', 'bonus2', 'bonus 2', 'allowance2', 'allowance 2', 'other income2', 'other income 2', 'overtime2', 'overtime 2'];
        $defaultBank = isset($bankSettings) ? $bankSettings->first() : null;
    @endphp

    <div class="tab-content" id="reportCategoryTabContent">
        @include('admin.reports._hub_payroll')
        @include('admin.reports._hub_statutory')
        @include('admin.reports._hub_banking')
        @include('admin.reports._hub_history')
        @include('admin.reports._hub_all_reports')
    </div>
</div>

@include('admin.reports._bank_settings_modal')
@include('admin.reports._cash_voucher_modal')
@include('admin.reports._hub_scripts')
@endsection
