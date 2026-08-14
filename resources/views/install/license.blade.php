@extends('install.layout')

@section('title', 'License')

@section('content')
    <h2>License</h2>
    <p class="lead">
        @if ($enabled)
            Enter your purchase code to verify this installation.
        @else
            Optional purchase code for your records. You can skip this step and continue.
        @endif
    </p>

    <form method="post" action="{{ route('install.license.store') }}">
        @csrf
        <div class="field">
            <label for="purchase_code">Purchase / license code</label>
            <input id="purchase_code" name="purchase_code" value="{{ old('purchase_code', $code) }}" placeholder="XXXX-XXXX-XXXX-XXXX">
            <div class="hint">Stored as APP_LICENSE_KEY when provided.</div>
        </div>

        <div class="actions">
            <a class="btn btn-ghost" href="{{ route('install.permissions') }}">Back</a>
            @if ($allowSkip)
                <button class="btn" type="submit" name="skip" value="1">Skip for now</button>
            @endif
            <button class="btn btn-primary" type="submit">Continue</button>
        </div>
    </form>
@endsection
