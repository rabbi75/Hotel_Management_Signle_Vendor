@extends('install.layout')

@section('title', 'Environment')

@section('content')
    <h2>Application environment</h2>
    <p class="lead">These values are written to <code>.env</code>. Mail can be configured later in the admin panel.</p>

    <form method="post" action="{{ route('install.environment.store') }}" id="environment-form">
        @csrf
        <div class="grid-2">
            <div class="field">
                <label for="app_name">Application name</label>
                <input id="app_name" name="app_name" value="{{ old('app_name', $environment['app_name']) }}" required>
            </div>
            <div class="field">
                <label for="app_url">Application URL</label>
                <input id="app_url" name="app_url" value="{{ old('app_url', $environment['app_url']) }}" required>
                <div class="hint">Must match this browser origin for cookies and OAuth callbacks.</div>
            </div>
        </div>

        <label class="toggle">
            <input type="checkbox" name="skip_mail" value="1" @checked(old('skip_mail', $environment['skip_mail'] ?? true)) id="skip_mail">
            Skip mail configuration for now
        </label>

        <div id="mail-fields">
            <div class="grid-2">
                <div class="field">
                    <label for="mail_mailer">Mailer</label>
                    <input id="mail_mailer" name="mail_mailer" value="{{ old('mail_mailer', $environment['mail_mailer']) }}">
                </div>
                <div class="field">
                    <label for="mail_host">Mail host</label>
                    <input id="mail_host" name="mail_host" value="{{ old('mail_host', $environment['mail_host']) }}">
                </div>
            </div>
            <div class="grid-2">
                <div class="field">
                    <label for="mail_port">Mail port</label>
                    <input id="mail_port" name="mail_port" type="number" value="{{ old('mail_port', $environment['mail_port']) }}">
                </div>
                <div class="field">
                    <label for="mail_from_address">From address</label>
                    <input id="mail_from_address" name="mail_from_address" type="email" value="{{ old('mail_from_address', $environment['mail_from_address']) }}">
                </div>
            </div>
            <div class="grid-2">
                <div class="field">
                    <label for="mail_username">Mail username</label>
                    <input id="mail_username" name="mail_username" value="{{ old('mail_username', $environment['mail_username']) }}">
                </div>
                <div class="field">
                    <label for="mail_password">Mail password</label>
                    <input id="mail_password" name="mail_password" type="password" value="{{ old('mail_password', $environment['mail_password']) }}">
                </div>
            </div>
            <div class="field">
                <label for="mail_from_name">From name</label>
                <input id="mail_from_name" name="mail_from_name" value="{{ old('mail_from_name', $environment['mail_from_name']) }}">
            </div>
        </div>

        <div class="actions">
            <a class="btn btn-ghost" href="{{ route('install.database') }}">Back</a>
            <button class="btn btn-primary" type="submit">Write environment</button>
        </div>
    </form>

    <script>
        const skip = document.getElementById('skip_mail');
        const mail = document.getElementById('mail-fields');
        function sync() { mail.style.display = skip.checked ? 'none' : 'block'; }
        skip.addEventListener('change', sync);
        sync();
    </script>
@endsection
