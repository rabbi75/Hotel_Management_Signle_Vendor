@extends('install.layout')

@section('title', 'Permissions')

@section('content')
    <h2>Writable paths</h2>
    <p class="lead">The installer must be able to write your environment file and storage directories.</p>

    <table>
        <thead>
            <tr>
                <th>Path</th>
                <th>Status</th>
                <th>Detail</th>
            </tr>
        </thead>
        <tbody>
            @foreach ($checks as $check)
                <tr>
                    <td><code>{{ $check['path'] }}</code></td>
                    <td>
                        @if ($check['ok'])
                            <span class="badge badge-ok">Writable</span>
                        @else
                            <span class="badge badge-fail">Blocked</span>
                        @endif
                    </td>
                    <td>{{ $check['detail'] }}</td>
                </tr>
            @endforeach
        </tbody>
    </table>

    <div class="actions">
        <a class="btn btn-ghost" href="{{ route('install.requirements') }}">Back</a>
        @if ($passes)
            <a class="btn btn-primary" href="{{ route('install.license') }}">Continue</a>
        @else
            <a class="btn btn-ghost" href="{{ route('install.permissions') }}">Re-check</a>
            <button class="btn btn-primary" type="button" disabled>Fix permissions to continue</button>
        @endif
    </div>
@endsection
