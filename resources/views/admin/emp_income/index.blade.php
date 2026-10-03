@extends('admin.layouts.Master')
@section('title') Employee Income Assignments @endsection
@section('content')
    <div class="col-12">
        <div class="card mt-4">
            <div class="card-header d-flex justify-content-between align-items-center">
                <h4 class="mb-0">Employee Income Assignments</h4>
                <a href="{{ route('emp-incomes.create') }}" class="btn btn-primary" style="background: linear-gradient(135deg, #1f618d, #2980b9); border: none; padding: 8px 18px; font-weight: bold; color: #fff;">
                    <i class="mdi mdi-plus"></i> Assign Income
                </a>
            </div>
            <div class="card-body">
                @if(session('success'))
                    <div class="alert alert-success alert-dismissible fade show" role="alert">
                        <strong>Success!</strong> {{ session('success') }}
                        <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
                    </div>
                @endif

                @if(session('error'))
                    <div class="alert alert-danger alert-dismissible fade show" role="alert">
                        <strong>Error!</strong> {{ session('error') }}
                        <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
                    </div>
                @endif

                <style>
                    #myTable th, #myTable td {
                        vertical-align: middle !important;
                        white-space: nowrap !important;
                    }
                </style>
                <div class="table-responsive">
                    <table class="table table-striped align-middle" id="myTable">
                        <thead>
                        <tr>
                            <th scope="col">Employee</th>
                            <th scope="col">Income Type</th>
                            <th scope="col">Amount</th>
                            <th scope="col">Frequency</th>
                            <th scope="col">Status</th>
                            <th scope="col">Action</th>
                        </tr>
                        </thead>
                        <tbody>
                            @if(isset($empIncomes))
                                @foreach ($empIncomes as $empIncome)
                                    <tr>
                                        <td>{{ $empIncome->employee->full_name }} ({{ $empIncome->employee->employee_code }})</td>
                                        <td><strong>{{ $empIncome->incomeType->name }}</strong></td>
                                        <td>PHP {{ number_format($empIncome->amount, 2) }}</td>
                                        <td>{{ ucfirst($empIncome->frequency) }}</td>
                                        <td>
                                            @if($empIncome->is_active)
                                                <span class="badge bg-success">Active</span>
                                            @else
                                                <span class="badge bg-secondary">Inactive</span>
                                            @endif
                                        </td>
                                        <td>
                                            <div class="d-flex align-items-center" style="gap: 12px; min-width: 80px;">
                                                <a href="{{ route('emp-incomes.edit', $empIncome->id) }}" class="text-warning" title="Edit" style="text-decoration: none;">
                                                    <i class="fa-solid fa-pen-to-square" style="font-size: 14px;"></i>
                                                </a>
                                                <form action="{{ route('emp-incomes.destroy', $empIncome->id) }}" method="POST" style="display:inline; margin:0;" class="btn-delete-form" onsubmit="return confirm('Are you sure you want to delete this income assignment?');">
                                                    @csrf
                                                    @method('DELETE')
                                                    <button type="submit" class="btn btn-link p-0 text-danger btn-delete" title="Delete" style="border: none; background: none; line-height: 1;">
                                                        <i class="fa-sharp fa-solid fa-trash" style="font-size: 14px;"></i>
                                                    </button>
                                                </form>
                                            </div>
                                        </td>    
                                    </tr>
                                @endforeach
                            @endif
                        </tbody>
                    </table>
                </div>
                <div class="d-flex justify-content-between align-items-center flex-wrap gap-2 mt-3">
                    <div>
                        {{ $empIncomes->links() }}
                    </div>
                    <x-per-page-selector :paginator="$empIncomes" />
                </div>
            </div> 
        </div> 
    </div>
    <script>
        var translations = {
            search: "{{ __('messages.search') }}"
        };
    </script>
@endsection
