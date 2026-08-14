@extends('install.layout')

@section('title', 'Finish')

@section('content')
    <h2>Installation complete</h2>
    <p class="lead">{{ config('saas.brand.name', 'Hotel Management') }} is ready. Sign in to the operator console to continue setup.</p>

    <h3 style="margin: 0 0 0.5rem; font-size: 1rem;">Post-install checklist</h3>
    <ul class="checklist">
        @foreach ($checklist as $item)
            <li>{{ $item }}</li>
        @endforeach
    </ul>

    <div class="actions">
        <a class="btn btn-primary" href="{{ $loginUrl }}">Go to admin login</a>
    </div>
@endsection
