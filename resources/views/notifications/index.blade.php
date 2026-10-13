@extends('layouts.app')
@section('content')
<div class="content-header"><div class="container-fluid d-flex justify-content-between"><h1>Notifications</h1>@if(auth()->user()->unreadNotifications()->exists())<form method="POST" action="{{ route('notifications.markAllAsRead') }}">@csrf<button class="btn btn-outline-primary">Mark all as read</button></form>@endif</div></div>
<section class="content"><div class="container-fluid"><div class="card"><div class="list-group list-group-flush">
@forelse($notifications as $notification)
 <a href="{{ route('notifications.read',$notification->id) }}" class="list-group-item list-group-item-action {{ $notification->read_at ? '' : 'bg-light' }}">
  <div class="d-flex"><i class="{{ $notification->data['icon'] ?? 'fas fa-info-circle' }} text-primary mt-1 mr-3"></i><div class="flex-grow-1"><div class="d-flex justify-content-between"><strong>{{ $notification->data['title'] ?? 'Notification' }}</strong><small class="text-muted">{{ $notification->created_at->diffForHumans() }}</small></div><div class="text-muted">{{ $notification->data['message'] ?? '' }}</div>@if(!$notification->read_at)<span class="badge badge-primary mt-1">New</span>@endif</div></div>
 </a>
@empty <div class="p-5 text-center text-muted">No notifications yet.</div> @endforelse
</div><div class="card-footer">{{ $notifications->links() }}</div></div></div></section>
@endsection
