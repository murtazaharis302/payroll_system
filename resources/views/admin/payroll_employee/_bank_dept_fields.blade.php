<h5 class="mt-4 mb-3">Department & Payroll Banking</h5>
<div class="row">
    <div class="col-md-6 mb-3">
        <label for="dept_id" class="form-label">Department (DeptID)</label>
        <select class="form-control" id="dept_id" name="dept_id">
            <option value="">Select Department</option>
            @foreach($departments as $dept)
                <option value="{{ $dept->id }}" {{ (string) old('dept_id', $selectedDeptId ?? '') === (string) $dept->id ? 'selected' : '' }}>
                    {{ $dept->name }} ({{ $dept->code }})
                </option>
            @endforeach
        </select>
        <small class="text-muted">
            <a href="{{ url('/' . panelPrefix() . '/departments') }}" target="_blank">Manage departments</a>
        </small>
        @error('dept_id')
            <span class="text-danger d-block">{{ $message }}</span>
        @enderror
    </div>

    <div class="col-md-6 mb-3">
        <label for="sub_dept_id" class="form-label">Sub-Department (SubDeptID)</label>
        <select class="form-control" id="sub_dept_id" name="sub_dept_id">
            <option value="">Select Sub-Department</option>
            @foreach($subDepartments as $subDept)
                <option value="{{ $subDept->id }}" {{ (string) old('sub_dept_id', $selectedSubDeptId ?? '') === (string) $subDept->id ? 'selected' : '' }}>
                    {{ $subDept->name }} ({{ $subDept->code }})
                </option>
            @endforeach
        </select>
        @error('sub_dept_id')
            <span class="text-danger d-block">{{ $message }}</span>
        @enderror
    </div>
</div>

<div class="row">
    <div class="col-md-6 mb-3">
        <label for="acct_num" class="form-label">Bank Account No. (AcctNum)</label>
        <input type="text" class="form-control" id="acct_num" name="acct_num" value="{{ old('acct_num', $acctNum ?? '') }}" maxlength="50">
        <small class="text-muted">For payroll bank upload file</small>
        @error('acct_num')
            <span class="text-danger d-block">{{ $message }}</span>
        @enderror
    </div>

    <div class="col-md-6 mb-3">
        <label for="atm_num" class="form-label">ATM Number (ATMNum)</label>
        <input type="text" class="form-control" id="atm_num" name="atm_num" value="{{ old('atm_num', $atmNum ?? '') }}" maxlength="50">
        <small class="text-muted">Additional bank info required by the bank</small>
        @error('atm_num')
            <span class="text-danger d-block">{{ $message }}</span>
        @enderror
    </div>
</div>

<div class="row">
    <div class="col-md-6 mb-3">
        <label for="tax_code" class="form-label">Tax Code</label>
        <select class="form-control" id="tax_code" name="tax_code">
            <option value="">Select Tax Code</option>
            @foreach($taxCodes as $code => $label)
                <option value="{{ $code }}" {{ old('tax_code', $taxCode ?? '') == $code ? 'selected' : '' }}>
                    {{ $label }}
                </option>
            @endforeach
        </select>
        @error('tax_code')
            <span class="text-danger d-block">{{ $message }}</span>
        @enderror
    </div>

    <div class="col-md-6 mb-3">
        <label for="remarks" class="form-label">Remarks</label>
        <textarea class="form-control" id="remarks" name="remarks" rows="2">{{ old('remarks', $remarks ?? '') }}</textarea>
        @error('remarks')
            <span class="text-danger d-block">{{ $message }}</span>
        @enderror
    </div>
</div>
