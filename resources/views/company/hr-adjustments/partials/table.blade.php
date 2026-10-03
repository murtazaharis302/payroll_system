<div class="table-responsive">
    <table class="table table-hover align-middle mb-0">
        <thead class="table-light">
            <tr>
                <th class="ps-3 font-12 fw-bold text-uppercase text-secondary">Effective Date</th>
                <th class="font-12 fw-bold text-uppercase text-secondary">Employee</th>
                <th class="font-12 fw-bold text-uppercase text-secondary">Adjustment Type</th>
                <th class="font-12 fw-bold text-uppercase text-secondary">Adjustment Details</th>
                <th class="font-12 fw-bold text-uppercase text-secondary">Reason / Notes</th>
                <th class="font-12 fw-bold text-uppercase text-secondary text-center">Status</th>
                <th class="pe-3 font-12 fw-bold text-uppercase text-secondary text-end">Actions</th>
            </tr>
        </thead>
        <tbody>
            @forelse($items as $adj)
            <tr>
                <td class="ps-3 font-13 fw-semibold text-dark">
                    {{ $adj->effective_date ? \Carbon\Carbon::parse($adj->effective_date)->format('M d, Y') : 'N/A' }}
                </td>
                <td>
                    @if($adj->employee)
                        <span class="fw-bold text-dark font-13 d-block">{{ $adj->employee->first_name }} {{ $adj->employee->last_name }}</span>
                        <span class="badge bg-soft-primary text-primary font-11">{{ $adj->employee->employee_code ?? 'EMP'.$adj->employee->id }}</span>
                    @else
                        <span class="text-muted font-12">Unassigned Employee</span>
                    @endif
                </td>
                <td>
                    @if($adj->adjustment_type === 'attendance')
                        <span class="badge bg-soft-info text-info font-12 px-2 py-1"><i class="fe-clock me-1"></i> Attendance</span>
                    @elseif($adj->adjustment_type === 'income')
                        <span class="badge bg-soft-success text-success font-12 px-2 py-1"><i class="fe-plus-circle me-1"></i> Income</span>
                    @elseif($adj->adjustment_type === 'deduction')
                        <span class="badge bg-soft-warning text-warning font-12 px-2 py-1"><i class="fe-minus-circle me-1"></i> Deduction</span>
                    @else
                        <span class="badge bg-soft-secondary text-secondary font-12 px-2 py-1">{{ ucfirst($adj->adjustment_type) }}</span>
                    @endif
                </td>
                <td class="font-13">
                    @if($adj->adjustment_type === 'attendance')
                        <span class="fw-semibold text-dark">Hours:</span> {{ number_format($adj->hours_worked, 2) }} hrs
                        @if($adj->overtime_hours > 0)
                            | <span class="fw-semibold text-dark">OT:</span> {{ number_format($adj->overtime_hours, 2) }} hrs
                        @endif
                    @elseif($adj->adjustment_type === 'income')
                        <span class="text-muted font-12">{{ $adj->incomeType?->name ?? 'Direct Additional Income' }}:</span>
                        <strong class="text-success ms-1">₱{{ number_format($adj->amount, 2) }}</strong>
                    @elseif($adj->adjustment_type === 'deduction')
                        <span class="text-muted font-12">{{ $adj->deductionType?->name ?? 'Direct Additional Deduction' }}:</span>
                        <strong class="text-danger ms-1">₱{{ number_format($adj->amount, 2) }}</strong>
                    @else
                        ₱{{ number_format($adj->amount ?? 0, 2) }}
                    @endif
                </td>
                <td class="font-12 text-muted max-w-250">
                    {{ Str::limit($adj->reason ?? $adj->notes ?? '—', 60) }}
                </td>
                <td class="text-center">
                    @if($adj->status === 'pending')
                        <span class="badge bg-warning text-white font-11 px-2 py-1 rounded-pill">Pending</span>
                    @elseif($adj->status === 'approved')
                        <span class="badge bg-success text-white font-11 px-2 py-1 rounded-pill">Approved</span>
                    @elseif($adj->status === 'rejected')
                        <span class="badge bg-danger text-white font-11 px-2 py-1 rounded-pill">Rejected</span>
                    @else
                        <span class="badge bg-secondary text-white font-11 px-2 py-1 rounded-pill">{{ ucfirst($adj->status) }}</span>
                    @endif
                </td>
                <td class="pe-3 text-end">
                    <div class="d-inline-flex gap-1">
                        @if($adj->status === 'pending')
                            <form action="{{ panelRoute('hr-adjustments.approve', $adj->id) }}" method="POST" class="d-inline">
                                @csrf
                                <button type="submit" class="btn btn-xs btn-outline-success font-11 fw-semibold" title="Approve Adjustment">
                                    <i class="fe-check me-1"></i> Approve
                                </button>
                            </form>
                        @endif
                        <form action="{{ panelRoute('hr-adjustments.destroy', $adj->id) }}" method="POST" class="d-inline" onsubmit="return confirm('Are you sure you want to delete this adjustment?');">
                            @csrf
                            @method('DELETE')
                            <button type="submit" class="btn btn-xs btn-outline-danger font-11" title="Delete Adjustment">
                                <i class="fe-trash-2"></i>
                            </button>
                        </form>
                    </div>
                </td>
            </tr>
            @empty
            <tr>
                <td colspan="7" class="text-center py-5 text-muted">
                    <div class="avatar-md bg-soft-secondary text-secondary rounded-circle mx-auto mb-2 d-flex align-items-center justify-content-center" style="width:48px; height:48px;">
                        <i class="fe-inbox font-22"></i>
                    </div>
                    <p class="mb-0 font-13 fw-semibold">No HR adjustments found in this category.</p>
                </td>
            </tr>
            @endforelse
        </tbody>
    </table>
</div>
