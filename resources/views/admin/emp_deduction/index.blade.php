@extends('admin.layouts.Master')
@section('title') Employee Deduction Assignments @endsection
@section('content')
    <div class="col-12">
        <div class="card mt-4">
            <div class="card-header">
                <h4>Employee Deduction Assignments</h4>
                <a href="{{ route('emp-deductions.create') }}" class="btn float-end" style="background-color: #f06115; color: white">Assign Deduction</a>
            </div>
            <div class="card-body">
                <table class="table table-striped" id="myTable">
                    <thead>
                    <tr>
                        <th scope="col">Employee</th>
                        <th scope="col">Deduction Type</th>
                        <th scope="col">Amount</th>
                        <th scope="col">Frequency</th>
                        <th scope="col">Status</th>
                        <th scope="col">Action</th>
                    </tr>
                    </thead>
                    <tbody>
                        @if(isset($empDeductions))
                            @foreach ($empDeductions as $empDeduction)
                                <tr>
                                    <td>{{ $empDeduction->employee->full_name }} ({{ $empDeduction->employee->employee_code }})</td>
                                    <td><strong>{{ $empDeduction->deductionType->name }}</strong></td>
                                    <td>₱ {{ number_format($empDeduction->amount, 2) }}</td>
                                    <td>{{ ucfirst($empDeduction->frequency) }}</td>
                                    <td>
                                        @if($empDeduction->is_active)
                                            <span class="badge bg-success">Active</span>
                                        @else
                                            <span class="badge bg-secondary">Inactive</span>
                                        @endif
                                    </td>
                                    <td>
                                        <a href="{{ route('emp-deductions.edit', $empDeduction->id) }}" title="Edit"><i class="fa-solid fa-pen-to-square"></i></a>
                                        <form action="{{ route('emp-deductions.destroy', $empDeduction->id) }}" method="POST" style="display:inline;" class="btn-delete-form">
                                            @csrf
                                            @method('DELETE')
                                            <button type="submit" class="btn btn-link p-0 btn-delete" style="color: red;" title="Delete">
                                                <i class="fa-sharp fa-solid fa-trash"></i>
                                            </button>
                                        </form>
                                    </td>    
                                </tr>
                            @endforeach
                        @endif
                    </tbody>
                </table>
                <div class="d-flex justify-content-between align-items-center flex-wrap gap-2 mt-3">
                    <div>
                        {{ $empDeductions->links() }}
                    </div>
                    <x-per-page-selector :paginator="$empDeductions" />
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
