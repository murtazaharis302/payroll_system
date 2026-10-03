
@extends('admin.layouts.Master')
@section('title') {{__('messages.admins') }} @endsection
@section('content')
    @if(auth()->user()->role == 'admin')
        <div class="col-12">
            <div class="card mt-4">
                <div class="card-header">
                    <h4>{{__('messages.admins') }}</h4>
                    @if(can('users','edit'))
                        <a href=" {{ panelRoute('createUser') }}" class="btn float-end" style="background-color: #f06115; color: white " >{{__('messages.create') }} {{__('messages.admin') }}</a>
                    @endif
                </div>
                <div class="card-body">
                    <div class="table-responsive">
                        <table class="table table-striped" id="myTable">
                        <thead>
                        <tr>
                            {{-- <th scope="col">Id</th> --}}
                            <th scope="col">{{__('messages.name') }}</th>
                            <th scope="col">{{__('messages.email') }}</th>
                            <th scope="col">{{__('messages.role') }}</th>
                            <th scope="col">{{__('messages.status') }}</th>
                            @if(can('users','edit'))
                                <th scope="col">{{__('messages.action') }}</th>
                            @endif
                        </tr>
                        </thead>
                        <tbody>
                            @if(isset($users))
                                @foreach ($users as $userdata)
                                    <tr>
                                        {{-- <td>{{ $userdata->id }}</td> --}}
                                        <td>{{ $userdata->name }}</td>
                                        <td>{{ $userdata->email }}</td>
                                        <td> {{ $userdata->role == 'admin' ? 'Admin' : ($userdata->role == 'user' ? 'User' : ($userdata->role == 'company' ? 'Company' : 'Unknown')) }}</td>
                                        <td>{{ $userdata->status == 1 ? 'Active' : 'Deactive' }}</td>                                           @if($userdata->id == $current_user)
                                             @if(can('users','edit'))
                                                 <td>
                                                     <a href="{{ panelRoute('editUser',$userdata->id) }}" class="disabled-link">
                                                         <i class="fa-solid fa-pen-to-square"></i>
                                                     </a>
                                                     <button type="button" class="btn btn-link p-0 text-danger border-0 disabled" disabled>
                                                         <i class="fa-sharp fa-solid fa-trash" style="color: red"></i>
                                                     </button>
                                                 </td>   
                                             @endif 
                                         @else
                                             @if(can('users','edit'))
                                                 <td>
                                                     <a href="{{ panelRoute('editUser',$userdata->id) }}"><i class="fa-solid fa-pen-to-square "></i></a>
                                                     <form action="{{ panelRoute('deleteUser', $userdata->id) }}" method="POST" class="d-inline" onsubmit="return confirm('Are you sure you want to delete this user?');">
                                                         @csrf
                                                         @method('DELETE')
                                                         <button type="submit" class="btn btn-link p-0 text-danger border-0">
                                                             <i class="fa-sharp fa-solid fa-trash" style="color: red"></i>
                                                         </button>
                                                     </form>
                                                 </td> 
                                             @endif   
                                         @endif        
                                    </tr>
                                @endforeach
                            @endif
                        </tbody>
                    </table>
                    </div>
                    {{ $users->links() }}
                </div> 
            </div> 
        </div>
        <script>
            var translations = {
                search: "{{ __('messages.search') }}"
            };
        </script>
    @else
        <div class="alert alert-danger" role="alert">
            Access Denied
        </div>
    @endif                         
@endsection

