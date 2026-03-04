@extends('layouts.app')

@section('title', 'Profile Settings')

@section('content')
<div class="container-fluid px-0" style="max-width: 760px;">
    <div class="card shadow-sm border-0">
        <div class="card-header bg-white border-0 pt-3 pb-0">
            <h1 class="h4 mb-1">Profile Management</h1>
            <p class="text-muted small mb-0">Update your account details and password.</p>
        </div>
        <div class="card-body">
            @if (session('status'))
                <div class="alert alert-success">{{ session('status') }}</div>
            @endif

            @if ($errors->any())
                <div class="alert alert-danger">
                    <ul class="mb-0">
                        @foreach ($errors->all() as $error)
                            <li>{{ $error }}</li>
                        @endforeach
                    </ul>
                </div>
            @endif

            <form method="POST" action="{{ route('profile.update') }}">
                @csrf
                @method('PUT')

                <div class="mb-3">
                    <label class="form-label" for="name">Full Name</label>
                    <input id="name" type="text" name="name" class="form-control" value="{{ old('name', $user->name) }}" required>
                </div>

                <div class="mb-3">
                    <label class="form-label" for="email">Email</label>
                    <input id="email" type="email" name="email" class="form-control" value="{{ old('email', $user->email) }}" required>
                </div>

                <div class="row g-3">
                    <div class="col-md-6">
                        <label class="form-label" for="password">New Password (optional)</label>
                        <input id="password" type="password" name="password" class="form-control" autocomplete="new-password">
                    </div>
                    <div class="col-md-6">
                        <label class="form-label" for="password_confirmation">Confirm New Password</label>
                        <input id="password_confirmation" type="password" name="password_confirmation" class="form-control" autocomplete="new-password">
                    </div>
                </div>

                <div class="d-flex justify-content-end mt-4">
                    <button type="submit" class="btn btn-primary">Save Profile</button>
                </div>
            </form>
        </div>
    </div>
</div>
@endsection
