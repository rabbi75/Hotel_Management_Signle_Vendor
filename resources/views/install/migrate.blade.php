@extends('install.layout')

@section('title', 'Migrate')

@section('content')
    <h2>Migrate &amp; seed</h2>
    <p class="lead">Creates the schema, roles, plans, settings, and landing content. Safe to retry if something fails.</p>

    @if ($output)
        <div class="code">{{ $output }}</div>
    @endif

    <form method="post" action="{{ route('install.migrate.run') }}">
        @csrf
        <div class="actions">
            <a class="btn btn-ghost" href="{{ route('install.environment') }}">Back</a>
            @if ($migrated)
                <a class="btn btn-primary" href="{{ route('install.admin') }}">Continue</a>
            @else
                <button class="btn btn-primary" type="submit">Run migrations</button>
            @endif
        </div>
    </form>
@endsection
