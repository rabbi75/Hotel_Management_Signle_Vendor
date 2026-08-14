@extends('install.layout')

@section('title', 'Database')

@section('content')
    <h2>Database connection</h2>
    <p class="lead">Credentials are tested with a live PDO connection before anything is written.</p>

    <form method="post" action="{{ route('install.database.store') }}">
        @csrf
        <div class="grid-2">
            <div class="field">
                <label for="host">Host</label>
                <input id="host" name="host" value="{{ old('host', $database['host']) }}" required>
            </div>
            <div class="field">
                <label for="port">Port</label>
                <input id="port" name="port" type="number" value="{{ old('port', $database['port']) }}" required>
            </div>
        </div>
        <div class="field">
            <label for="database">Database name</label>
            <input id="database" name="database" value="{{ old('database', $database['database']) }}" required>
            <div class="hint">Create an empty MySQL 8 / MariaDB database first.</div>
        </div>
        <div class="grid-2">
            <div class="field">
                <label for="username">Username</label>
                <input id="username" name="username" value="{{ old('username', $database['username']) }}" required>
            </div>
            <div class="field">
                <label for="password">Password</label>
                <input id="password" name="password" type="password" value="{{ old('password', $database['password']) }}">
            </div>
        </div>

        <div class="actions">
            <a class="btn btn-ghost" href="{{ route('install.license') }}">Back</a>
            <button class="btn btn-primary" type="submit">Test &amp; continue</button>
        </div>
    </form>
@endsection
