@extends('layouts.app')

@section('title', 'Change Temporary Password')

@section('content')
<div class="container py-5">
    <div class="row justify-content-center">
        <div class="col-md-6 col-lg-5">
            <div class="card card-warning shadow">
                <div class="card-header"><h3 class="card-title">Change Your Temporary Password</h3></div>
                <form method="POST" action="{{ route('password.first.update') }}">
                    @csrf
                    @method('PUT')
                    <div class="card-body">
                        <div class="alert alert-info">
                            For security, you must replace the temporary password before using the system.
                        </div>
                        @if($errors->any())
                            <div class="alert alert-danger"><ul class="mb-0">@foreach($errors->all() as $error)<li>{{ $error }}</li>@endforeach</ul></div>
                        @endif
                        <div class="form-group">
                            <label>Current temporary password</label>
                            <input type="password" name="current_password" class="form-control" required autofocus>
                        </div>
                        <div class="form-group">
                            <label>New password</label>
                            <input type="password" name="password" class="form-control" required>
                        </div>
                        <div class="form-group">
                            <label>Confirm new password</label>
                            <input type="password" name="password_confirmation" class="form-control" required>
                        </div>
                    </div>
                    <div class="card-footer">
                        <button type="submit" class="btn btn-warning font-weight-bold">Change Password and Continue</button>
                        <button type="submit" form="logout-form" class="btn btn-outline-secondary float-right">Log out</button>
                    </div>
                </form>
                <form id="logout-form" method="POST" action="{{ route('logout') }}" class="d-none">@csrf</form>
            </div>
        </div>
    </div>
</div>
@endsection
