<!-- Cash Voucher Pre-Generation Filter Modal -->
<div class="modal fade" id="cashVoucherModal" tabindex="-1" aria-labelledby="cashVoucherModalLabel" aria-hidden="true">
    <div class="modal-dialog modal-lg modal-dialog-centered">
        <div class="modal-content border-0 shadow">
            <form action="{{ panelRoute('reports.voucher') }}" method="POST" target="_blank">
                @csrf
                @if(isset($selectedCompanyId)) <input type="hidden" name="company_id" value="{{ $selectedCompanyId }}"> @endif
                <div class="modal-header bg-primary text-white py-2">
                    <h5 class="modal-title font-16 fw-bold text-white" id="cashVoucherModalLabel">
                        <i class="fe-sliders me-2"></i> Cash Voucher Pre-Generation Filters
                    </h5>
                    <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                <div class="modal-body p-3">
                    <div class="row g-2 mb-2">
                        <div class="col-md-6">
                            <label class="form-label font-12 fw-semibold">Payroll Period <span class="text-danger">*</span></label>
                            <x-payroll-period-select name="payroll_period_id" required />
                        </div>
                        <div class="col-md-6">
                            <label class="form-label font-12 fw-semibold">Department</label>
                            <select name="department_id" class="form-select form-select-sm">
                                <option value="all" selected>All Active Employees (Company-Wide)</option>
                                @php $targetDepts = isset($departments) ? $departments : \App\Models\Department::where('is_active', true)->when(isset($selectedCompanyId) && $selectedCompanyId, fn($q) => $q->where('company_id', $selectedCompanyId))->orderBy('name')->get(); @endphp
                                @foreach($targetDepts as $dept)
                                <option value="{{ $dept->id }}">{{ $dept->name }}</option>
                                @endforeach
                            </select>
                        </div>
                    </div>

                    <div class="row g-2 mb-2">
                        <div class="col-md-6">
                            <label class="form-label font-12 fw-semibold">Voucher Type</label>
                            <input type="text" name="voucher_type" class="form-control form-control-sm" value="Salaries & Wages" placeholder="e.g., Salaries & Wages">
                        </div>
                        <div class="col-md-6">
                            <label class="form-label font-12 fw-semibold">Voucher Date</label>
                            <input type="date" name="voucher_date" class="form-control form-control-sm" value="{{ date('Y-m-d') }}">
                        </div>
                    </div>

                    <div class="form-group mb-3">
                        <label class="form-label font-12 fw-semibold">Description / Particulars Header</label>
                        <input type="text" name="description" class="form-control form-control-sm" value="To payment for salaries and wages" placeholder="Description for single summary line">
                    </div>

                    <div class="card bg-light border-0 p-2 mb-3">
                        <div class="row">
                            <div class="col-md-6">
                                <div class="form-check form-switch mb-1">
                                    <input type="hidden" name="display_breakdown" value="0">
                                    <input class="form-check-input" type="checkbox" name="display_breakdown" value="1" id="displayBreakdownCheck" checked>
                                    <label class="form-check-label font-12 fw-semibold" for="displayBreakdownCheck">
                                        Display Itemized Income Breakdown
                                    </label>
                                </div>
                                <small class="text-muted font-11 d-block">If unchecked, renders a single line item with the total earnings.</small>
                            </div>
                            <div class="col-md-6">
                                <div class="form-check form-switch mb-1">
                                    <input class="form-check-input" type="checkbox" name="include_zero" value="1" id="includeZeroCheck">
                                    <label class="form-check-label font-12 fw-semibold" for="includeZeroCheck">
                                        Include Zero Income Line Items
                                    </label>
                                </div>
                                <small class="text-muted font-11 d-block">Includes earnings line-items with 0.00 balances.</small>
                            </div>
                        </div>
                    </div>

                    <!-- Income Visibility Checklist Grid -->
                    <div class="form-group mb-2">
                        <label class="form-label font-12 fw-semibold d-flex justify-content-between align-items-center">
                            <span><i class="fe-check-square me-1"></i> Income Type Visibility Checklist</span>
                            <span class="font-11 text-muted fw-normal">(Check income items to include in voucher)</span>
                        </label>
                        <div class="border rounded p-2 bg-white" style="max-height: 160px; overflow-y: auto;">
                            <div class="row g-2">
                                @if(isset($incomeTypes) && count($incomeTypes) > 0)
                                    @foreach($incomeTypes as $incType)
                                    <div class="col-md-4 col-6">
                                        <div class="form-check">
                                            <input class="form-check-input" type="checkbox" name="income_types[]" value="{{ $incType->id }}" id="incType_{{ $incType->id }}" checked>
                                            <label class="form-check-label font-12 text-dark" for="incType_{{ $incType->id }}">
                                                {{ $incType->name }}
                                            </label>
                                        </div>
                                    </div>
                                    @endforeach
                                @else
                                    <div class="col-12 text-muted font-12">All active income types will be automatically included.</div>
                                @endif
                            </div>
                        </div>
                    </div>
                </div>
                <div class="modal-footer py-2 bg-light">
                    <button type="button" class="btn btn-sm btn-secondary" data-bs-dismiss="modal">Cancel</button>
                    <button type="submit" class="btn btn-sm btn-primary fw-semibold">
                        <i class="fe-printer me-1"></i> Generate 2-Up Cash Vouchers PDF
                    </button>
                </div>
            </form>
        </div>
    </div>
</div>
