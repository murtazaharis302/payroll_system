@extends('admin.layouts.Master')
@section('title')
{{ __('messages.notifications') }}
@endsection

@section('content')

<div class="row">
    <div class="col-xl-12">
        <div class="card">
            <div class="card-body">
                <div class="dropdown float-end">
                    <a href="#" class="dropdown-toggle arrow-none card-drop" data-bs-toggle="dropdown" aria-expanded="false">
                        <i class="mdi mdi-dots-vertical"></i>
                    </a>
                    <div class="dropdown-menu dropdown-menu-end">
                        <a href="{{route('notifications.clear')}}" class="dropdown-item">{{ __('messages.mark_as') }}</a>
                        <!-- <a href="javascript:void(0);" class="dropdown-item">Settings</a> -->
                    </div>
                </div>
                
                <h4 class="header-title mb-3">({{count($notifications)}}) {{ __('messages.notifications') }}</h4>
                <div class="inbox-widget" data-simplebar style="max-height: 407px;">
                    @forelse ($notifications_data as $notification)
                        <div class="inbox-item">
                            <div class="inbox-item-img">
                                <img src="{{ $notification->from_user && $notification->from_user->profile_image 
                                    ? asset($notification->from_user->profile_image)
                                    : asset('assets/images/profile/user.png') }}" 
                                    class="rounded-circle" alt="">
                            </div>
                            <p class="inbox-item-author">
                                {{ $notification->from_user->name ?? 'System' }}
                                
                                @if($notification->status == 0)
                                <a href="{{ route('notifications.read', $notification->id) }}" >
                                    <i class="fa-solid fa-envelope" style="color: green;font-size:15px;"></i>
                                </a>
                                @else
                                <a href="{{ route('notifications.read', $notification->id) }}" >
                                    <i class="fa-solid fa-envelope-open" style="color: red;font-size:15px;"></i>
                                </a>
                                @endif
                            </p>
                            <p>{{ $notification->text }}</p>
                            
                            <p class="inbox-item-date">
                                <small class="text-muted">{{ $notification->created_at->diffForHumans() }}</small>
                            </p>
                        </div>
                    @empty
                        <p class="text-center text-muted">{{ __('messages.not_found') }}</p>
                    @endforelse
                </div>
                {{ $notifications_data->links() }}
            </div>
        </div> <!-- end card -->
    </div> <!-- end col -->
</div>


@endsection
