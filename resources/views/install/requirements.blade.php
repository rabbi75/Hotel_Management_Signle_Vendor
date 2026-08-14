@extends('install.layout')

@section('title', 'Requirements')

@section('content')
    <h2>Server requirements</h2>
    <p class="lead">These checks must pass before configuration begins. Optional items are recommended but not blocking.</p>

    <table>
        <thead>
            <tr>
                <th>Check</th>
                <th>Status</th>
                <th>Detail</th>
            </tr>
        </thead>
        <tbody>
            @foreach ($checks as $check)
                <tr>
                    <td>{{ $check['label'] }}</td>
                    <td>
                        @if ($check['ok'])
                            <span class="badge badge-ok">Pass</span>
                        @elseif ($check['required'])
                            <span class="badge badge-fail">Required</span>
                        @else
                            <span class="badge badge-optional">Optional</span>
                        @endif
                    </td>
                    <td>{{ $check['detail'] }}</td>
                </tr>
            @endforeach
        </tbody>
    </table>

    <div class="actions">
        @if ($passes)
            <a class="btn btn-primary" href="{{ route('install.permissions') }}">Continue</a>
        @else
            <button class="btn btn-primary" type="button" disabled>Fix required items to continue</button>
            <a class="btn btn-ghost" href="{{ route('install.requirements') }}">Re-check</a>
        @endif
    </div>
@endsection
