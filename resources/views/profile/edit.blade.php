@extends('layouts.app')

@section('title', 'User Profile')

@section('breadcrumb')
    <li class="breadcrumb-item active">Profile</li>
@endsection

@section('content')
    <div class="row">
        <div class="col-md-4">
            <!-- Profile Overview -->
            <div class="card card-primary card-outline shadow-sm mb-4">
                <div class="card-body box-profile">
                    <div class="text-center">
                        <div class="bg-light d-inline-block rounded-circle p-4 mb-3">
                            <i class="fas fa-user-circle fa-5x text-secondary"></i>
                        </div>
                    </div>
                    <h3 class="profile-username text-center font-weight-bold">{{ auth()->user()->name }}</h3>
                    <p class="text-muted text-center">{{ ucfirst(auth()->user()->getRoleNames()->first() ?? 'Staff') }}</p>

                    <ul class="list-group list-group-unbordered mb-3">
                        <li class="list-group-item">
                            <b>Email</b> <a class="float-right text-dark">{{ auth()->user()->email }}</a>
                        </li>
                        <li class="list-group-item">
                            <b>Department</b> <a
                                class="float-right text-dark">{{ auth()->user()->employee?->department?->name ?? 'N/A' }}</a>
                        </li>
                    </ul>
                </div>
            </div>

            <!-- Recent Activity Placeholder -->
            <div class="card shadow-sm">
                <div class="card-header bg-white font-weight-bold">
                    <i class="fas fa-history mr-2 text-muted"></i> Recent Activity
                </div>
                <div class="card-body p-0">
                    <ul class="nav nav-pills flex-column">
                        <li class="nav-item">
                            <span class="nav-link text-sm">
                                Last login: {{ now()->diffForHumans() }}
                            </span>
                        </li>
                        <li class="nav-item">
                            <span class="nav-link text-sm">
                                Account type: {{ ucfirst(auth()->user()->getRoleNames()->first()) }}
                            </span>
                        </li>
                    </ul>
                </div>
            </div>
        </div>

        <div class="col-md-8">
            <div class="card card-primary card-outline card-tabs shadow-sm">
                <div class="card-header p-0 pt-1 border-bottom-0">
                    <ul class="nav nav-tabs" id="profileTab" role="tablist">
                        <li class="nav-item">
                            <a class="nav-link active" id="info-tab" data-toggle="pill" href="#info" role="tab">Personal
                                Information</a>
                        </li>
                        <li class="nav-item">
                            <a class="nav-link" id="security-tab" data-toggle="pill" href="#security" role="tab">Security &
                                Password</a>
                        </li>
                        <li class="nav-item">
                            <a class="nav-link" id="notifications-tab" data-toggle="pill" href="#notifications"
                                role="tab">Notifications</a>
                        </li>
                    </ul>
                </div>
                <div class="card-body">
                    <div class="tab-content" id="profileTabContent">
                        <!-- Info Tab -->
                        <div class="tab-pane fade show active" id="info" role="tabpanel">
                            <form action="{{ route('profile.info.update') }}" method="POST">
                                @csrf
                                @method('PUT')
                                <div class="form-group">
                                    <label>Full Name</label>
                                    <input type="text" name="name" class="form-control" value="{{ auth()->user()->name }}"
                                        required>
                                </div>
                                <div class="form-group">
                                    <label>Email Address</label>
                                    <input type="email" name="email" class="form-control"
                                        value="{{ auth()->user()->email }}" required>
                                </div>
                                <div class="text-right">
                                    <button type="submit" class="btn btn-primary px-4 shadow-sm">Save Changes</button>
                                </div>
                            </form>
                        </div>

                        <!-- Security Tab -->
                        <div class="tab-pane fade" id="security" role="tabpanel">
                            <form action="{{ route('profile.password.update') }}" method="POST">
                                @csrf
                                @method('PUT')
                                <div class="form-group">
                                    <label>Current Password</label>
                                    <input type="password" name="current_password" class="form-control"
                                        placeholder="Enter current password" required>
                                </div>
                                <hr>
                                <div class="form-group">
                                    <label>New Password</label>
                                    <input type="password" name="password" class="form-control"
                                        placeholder="Minimum 8 characters" required>
                                </div>
                                <div class="form-group">
                                    <label>Confirm New Password</label>
                                    <input type="password" name="password_confirmation" class="form-control"
                                        placeholder="Repeat new password" required>
                                </div>
                                <div class="text-right">
                                    <button type="submit"
                                        class="btn btn-warning px-4 shadow-sm text-dark font-weight-bold">Update
                                        Password</button>
                                </div>
                            </form>
                        </div>

                        <!-- Notifications Tab -->
                        <div class="tab-pane fade" id="notifications" role="tabpanel">
                            <div class="d-flex justify-content-between align-items-center mb-3">
                                <h5 class="mb-0">Your Notifications</h5>
                                <form action="{{ route('notifications.markAllAsRead') }}" method="POST">
                                    @csrf
                                    <button type="submit" class="btn btn-sm btn-outline-secondary">Mark all as read</button>
                                </form>
                            </div>
                            <div class="list-group list-group-flush">
                                @forelse(auth()->user()->notifications as $notification)
                                    <div class="list-group-item px-0 {{ $notification->unread() ? 'bg-light' : '' }}">
                                        <div class="d-flex w-100 justify-content-between">
                                            <h6 class="mb-1 font-weight-bold">
                                                <i class="{{ $notification->data['icon'] ?? 'fas fa-info-circle' }} mr-2"></i>
                                                {{ $notification->data['title'] }}
                                            </h6>
                                            <small class="text-muted">{{ $notification->created_at->diffForHumans() }}</small>
                                        </div>
                                        <p class="mb-1 text-sm text-muted">{{ $notification->data['message'] }}</p>
                                        @if($notification->unread())
                                            <a href="{{ route('notifications.read', $notification->id) }}"
                                                class="text-xs text-primary">Mark as read</a>
                                        @endif
                                    </div>
                                @empty
                                    <div class="text-center py-4">
                                        <i class="far fa-bell fa-2x text-muted opacity-25 mb-2"></i>
                                        <p class="text-muted mb-0">No notifications yet.</p>
                                    </div>
                                @endforelse
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
@endsection