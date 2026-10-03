@extends('admin.layouts.Master')
@section('title') {{ __('messages.company') }} @endsection
@section('content')
    @if(auth()->user()->role == 'admin')
        <style>
            .tooltip-icon {
            position: relative;
            cursor: pointer;
            }

            .tooltip-icon .tooltip-text {
            visibility: hidden;
            background-color: #f06115;
            color: #fff;
            padding: 6px;
            border-radius: 4px;
            font-size: 12px;
            position: absolute;
            top: -5px;
            left: 20px;
            white-space: nowrap;
            z-index: 1;
            }

            .tooltip-icon:hover .tooltip-text {
            visibility: visible;
            }
        </style>
        <div class="col-12">
            <div class="card mt-4">
                <div class="card-header">
                    <h4>{{ __('messages.pending') }} {{ __('messages.companies') }}</h4>
                    
                </div>
                <div class="card-body">
                    <table class="table table-striped" id="myTable">
                        <thead>
                        <tr>
                            <th scope="col">{{ __('messages.name') }}</th>
                            <th scope="col">Company Code</th>
                            <th scope="col">{{ __('messages.company') }} {{ __('messages.name') }}</th>
                            <th scope="col">{{ __('messages.company') }} {{ __('messages.email') }}</th>
                            <th scope="col">{{ __('messages.personal') }} {{ __('messages.email') }}
                                <span class="tooltip-icon">
                                    <i>ℹ️</i>
                                    <span class="tooltip-text">{{ __('messages.login_email') }}</span>
                                </span>
                            </th>
                            <th scope="col">{{ __('messages.phone') }}</th>
                            <th scope="col">{{ __('messages.website') }}</th>
                            @if(can('companies','edit'))
                                <th scope="col">{{ __('messages.action') }}</th>
                            @endif
                        </tr>
                        </thead>
                        <tbody id="companytable">
                            @if(isset($companies))
                                @foreach ($companies as $compdata)
                                    <tr>
                                        <td>{{ $compdata->user->name }}</td>
                                        <td><span class="badge bg-soft-primary text-primary font-12 fw-semibold">{{ $compdata->company_code ?? 'N/A' }}</span></td>
                                        <td>{{ $compdata->name }}</td>
                                        <td>{{ $compdata->email }}</td>
                                        <td>{{ $compdata->user->email }}</td>
                                        <td>{{ $compdata->phone }}</td>
                                        <td>{{ $compdata->website }}</td>
                                        @if(can('companies','edit'))
                                             <td>
                                                 <form action="{{ panelRoute('aprovePendingCompany', $compdata->id) }}" method="POST" class="d-inline">
                                                     @csrf
                                                     <button type="submit" class="btn btn-link p-0 text-success border-0" title="Approve">
                                                         <i class="fa-regular fa-circle-check pending-btn"></i>
                                                     </button>
                                                 </form>
                                                 <form action="{{ panelRoute('destroyPendingCompany', $compdata->id) }}" method="POST" class="d-inline" onsubmit="return confirm('Are you sure you want to delete this pending company?');">
                                                     @csrf
                                                     @method('DELETE')
                                                     <button type="submit" class="btn btn-link p-0 text-danger border-0" title="Delete">
                                                         <i class="fa-sharp fa-solid fa-trash fa-1x delete-btn"></i>
                                                     </button>
                                                 </form>
                                             </td>   
                                        @endif 
                                    </tr>
                                @endforeach
                            @endif
                        </tbody>
                    </table>
                    {{ $companies->links() }}
                </div> 
            </div> 
        </div>     
    @else
        <div class="alert alert-danger" role="alert">
            Access Denied
        </div>
    @endif  
@endsection

