@extends('admin.layouts.Master')
@section('title') Departments @endsection
@section('content')
    <div class="col-12">
        <div class="card mt-4">
            <div class="card-header">
                <h4>Departments & Sub-Departments</h4>
                <a href="{{ panelRoute('payroll-employees.index') }}" class="btn float-end" style="background-color: #f06115; color: white">Back to Payroll Employees</a>
            </div>
            <div class="card-body">
                @if(in_array(auth()->user()->role, ['admin', 'company']) && $companies->count() > 1)
                <form method="GET" class="row mb-4">
                    <div class="col-md-6">
                        <label class="form-label">Filter by Company</label>
                        <select name="company_id" class="form-control" onchange="this.form.submit()">
                            @foreach($companies as $company)
                                <option value="{{ $company->id }}" {{ (int) $selectedCompanyId === (int) $company->id ? 'selected' : '' }}>
                                    {{ $company->name }}
                                </option>
                            @endforeach
                        </select>
                    </div>
                </form>
                @endif

                <div class="row">
                    <div class="col-md-5">
                        <h5>Add Department</h5>
                        <form method="POST" action="{{ panelRoute('departments.store') }}">
                            @csrf
                            <input type="hidden" name="company_id" value="{{ $selectedCompanyId }}">
                            <div class="mb-3">
                                <label class="form-label">Code</label>
                                <input type="text" name="code" class="form-control" placeholder="e.g. OPS" required>
                            </div>
                            <div class="mb-3">
                                <label class="form-label">Name</label>
                                <input type="text" name="name" class="form-control" placeholder="e.g. Operations" required>
                            </div>
                            <button type="submit" class="btn btn-primary">Add Department</button>
                        </form>
                    </div>

                    <div class="col-md-7">
                        <h5>Existing Departments</h5>
                        <div class="table-responsive">
                            <table class="table table-bordered">
                                <thead>
                                    <tr>
                                        <th>Code</th>
                                        <th>Name</th>
                                        <th>Sub-Departments</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    @forelse($departments as $dept)
                                        <tr>
                                            <td>{{ $dept->code }}</td>
                                            <td>{{ $dept->name }}</td>
                                            <td>
                                                @if($dept->subDepartments->count())
                                                    <ul class="mb-0">
                                                        @foreach($dept->subDepartments as $sub)
                                                            <li>{{ $sub->code }} - {{ $sub->name }}</li>
                                                        @endforeach
                                                    </ul>
                                                @else
                                                    <span class="text-muted">None</span>
                                                @endif
                                            </td>
                                        </tr>
                                    @empty
                                        <tr>
                                            <td colspan="3" class="text-center text-muted">No departments yet. Add one on the left.</td>
                                        </tr>
                                    @endforelse
                                </tbody>
                            </table>
                        </div>
                    </div>
                </div>

                @if($departments->count())
                <hr>
                <h5 class="mt-3">Add Sub-Department</h5>
                <form method="POST" action="{{ panelRoute('sub-departments.store') }}" class="row">
                    @csrf
                    <input type="hidden" name="company_id" value="{{ $selectedCompanyId }}">
                    <div class="col-md-4 mb-3">
                        <label class="form-label">Parent Department</label>
                        <select name="department_id" class="form-control" required>
                            @foreach($departments as $dept)
                                <option value="{{ $dept->id }}">{{ $dept->name }}</option>
                            @endforeach
                        </select>
                    </div>
                    <div class="col-md-3 mb-3">
                        <label class="form-label">Code</label>
                        <input type="text" name="code" class="form-control" required>
                    </div>
                    <div class="col-md-3 mb-3">
                        <label class="form-label">Name</label>
                        <input type="text" name="name" class="form-control" required>
                    </div>
                    <div class="col-md-2 mb-3 d-flex align-items-end">
                        <button type="submit" class="btn btn-primary w-100">Add</button>
                    </div>
                </form>
                @endif
            </div>
        </div>
    </div>
@endsection
