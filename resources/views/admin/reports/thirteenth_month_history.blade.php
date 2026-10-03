@extends('admin.layouts.app')

@section('title', '13th Month Payroll History Report')

@section('content')
<div class="container-fluid">
    <div class="d-flex justify-content-between align-items-center mb-4">
        <div>
            <h4 class="mb-1 text-dark fw-bold">13th Month Payroll History Report</h4>
            <p class="text-muted mb-0 small">Legacy Cutoff View & 13th Month Pay (Total Basic ÷ 11.5)</p>
        </div>
        <div class="d-flex gap-2">
            <a href="{{ panelRoute('reports.thirteenth-month-history.pdf', ['year' => $year, 'company_id' => $companyId]) }}" target="_blank" class="btn btn-outline-primary btn-sm">
                <i class="mdi mdi-printer me-1"></i> Print / PDF
            </a>
            <a href="{{ panelRoute('reports.thirteenth-month-history.excel', ['year' => $year, 'company_id' => $companyId]) }}" class="btn btn-success btn-sm">
                <i class="mdi mdi-file-excel me-1"></i> Export Excel
            </a>
        </div>
    </div>

    <!-- Filter Card -->
    <div class="card mb-4 border-0 shadow-sm">
        <div class="card-body">
            <form action="{{ panelRoute('reports.thirteenth-month-history') }}" method="GET" class="row g-3 align-items-end">
                @if($isSuperAdmin && count($companies) > 0)
                    <div class="col-md-4">
                        <label class="form-label font-weight-bold">Select Company</label>
                        <select name="company_id" class="form-select">
                            @foreach($companies as $c)
                                <option value="{{ $c->id }}" {{ (int)$companyId === (int)$c->id ? 'selected' : '' }}>
                                    {{ $c->name }}
                                </option>
                            @endforeach
                        </select>
                    </div>
                @endif

                <div class="col-md-3">
                    <label class="form-label font-weight-bold">Select Year</label>
                    <select name="year" class="form-select">
                        @for($y = date('Y'); $y >= date('Y') - 5; $y--)
                            <option value="{{ $y }}" {{ (int)$year === (int)$y ? 'selected' : '' }}>
                                {{ $y }}
                            </option>
                        @endfor
                    </select>
                </div>

                <div class="col-md-2">
                    <button type="submit" class="btn btn-primary w-100">
                        <i class="mdi mdi-filter me-1"></i> Filter
                    </button>
                </div>
            </form>
        </div>
    </div>

    <!-- Report Table Card -->
    <div class="card border-0 shadow-sm">
        <div class="card-body p-0">
            <div class="table-responsive">
                <table class="table table-hover table-striped table-bordered align-middle mb-0" style="font-size: 13px;">
                    <thead class="table-dark">
                        <tr>
                            <th>NAME</th>
                            @foreach($reportData['cutoffs'] as $cData)
                                <th class="text-end" style="min-width: 90px;">{{ $cData['short_label'] }}</th>
                            @endforeach
                            <th class="text-end bg-primary text-white">TOTAL</th>
                            <th class="text-end bg-success text-white">13TH MONTH PAY</th>
                        </tr>
                    </thead>
                    <tbody>
                        @forelse($reportData['rows'] as $row)
                            <tr>
                                <td class="fw-bold">{{ $row['employee_name'] }}</td>
                                @foreach($reportData['cutoffs'] as $pId => $cData)
                                    @php $val = (float)($row['cutoff_values'][$pId] ?? 0); @endphp
                                    <td class="text-end {{ $val > 0 ? '' : 'text-muted' }}">
                                        {{ $val > 0 ? number_format($val, 2) : '0.00' }}
                                    </td>
                                @endforeach
                                <td class="text-end fw-bold text-primary">
                                    {{ number_format($row['total_basic'], 2) }}
                                </td>
                                <td class="text-end fw-bold text-success">
                                    {{ number_format($row['thirteenth_month_pay'], 2) }}
                                </td>
                            </tr>
                        @empty
                            <tr>
                                <td colspan="{{ count($reportData['cutoffs']) + 3 }}" class="text-center py-4 text-muted">
                                    <i class="mdi mdi-alert-circle-outline me-1"></i> No approved payroll records found for the selected company and year.
                                </td>
                            </tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
        </div>
    </div>
</div>
@endsection
