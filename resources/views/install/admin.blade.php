@extends('install.layout')

@section('title', 'Admin')

@section('content')
    <h2>Platform administrator</h2>
    <p class="lead">This account signs in at <code>/admin/login</code> and manages tenants, billing, and system settings.</p>

    <form method="post" action="{{ route('install.admin.store') }}">
        @csrf
        <div class="field">
            <label for="name">Full name</label>
            <input id="name" name="name" value="{{ old('name', $admin['name']) }}" required>
        </div>
        <div class="field">
            <label for="email">Email</label>
            <input id="email" name="email" type="email" value="{{ old('email', $admin['email']) }}" required>
        </div>
        <div class="grid-2">
            <div class="field">
                <label for="password">Password</label>
                <input id="password" name="password" type="password" required minlength="8">
            </div>
            <div class="field">
                <label for="password_confirmation">Confirm password</label>
                <input id="password_confirmation" name="password_confirmation" type="password" required minlength="8">
            </div>
        </div>

        <div class="actions">
            <a class="btn btn-ghost" href="{{ route('install.migrate') }}">Back</a>
            <button class="btn btn-primary" type="submit">Create admin &amp; finish</button>
        </div>
    </form>
@endsection
