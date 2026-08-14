<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <title>@yield('title', 'Installer') · {{ config('saas.brand.name', 'Hotel Management') }}</title>
    <style>
        :root {
            --bg: #0f1714;
            --bg-elevated: #16201c;
            --panel: #1c2923;
            --border: #2a3b33;
            --text: #e8f0eb;
            --muted: #9bb0a5;
            --accent: #3d9b6e;
            --accent-strong: #2f7a56;
            --danger: #d46868;
            --warning: #d4a24c;
            --ok: #4caf82;
            --radius: 12px;
            --font: "Segoe UI", system-ui, -apple-system, sans-serif;
        }
        * { box-sizing: border-box; }
        body {
            margin: 0;
            min-height: 100vh;
            font-family: var(--font);
            color: var(--text);
            background:
                radial-gradient(1200px 600px at 10% -10%, rgba(61, 155, 110, 0.18), transparent 55%),
                radial-gradient(900px 500px at 100% 0%, rgba(45, 90, 120, 0.16), transparent 50%),
                var(--bg);
        }
        a { color: var(--accent); text-decoration: none; }
        .shell {
            width: min(920px, calc(100% - 2rem));
            margin: 0 auto;
            padding: 2.5rem 0 3rem;
        }
        .brand {
            display: flex;
            align-items: center;
            gap: 0.75rem;
            margin-bottom: 1.75rem;
        }
        .brand-mark {
            width: 2.25rem;
            height: 2.25rem;
            border-radius: 0.6rem;
            background: linear-gradient(145deg, var(--accent), #1f5c40);
            display: grid;
            place-items: center;
            font-weight: 700;
            font-size: 0.85rem;
        }
        .brand h1 {
            margin: 0;
            font-size: 1.1rem;
            font-weight: 600;
            letter-spacing: 0.01em;
        }
        .brand p {
            margin: 0.15rem 0 0;
            color: var(--muted);
            font-size: 0.85rem;
        }
        .stepper {
            display: grid;
            grid-template-columns: repeat(8, minmax(0, 1fr));
            gap: 0.35rem;
            margin-bottom: 1.5rem;
        }
        .step {
            text-align: center;
            font-size: 0.68rem;
            color: var(--muted);
            padding: 0.45rem 0.2rem;
            border-radius: 999px;
            border: 1px solid transparent;
            background: transparent;
        }
        .step.done { color: var(--ok); background: rgba(76, 175, 130, 0.08); }
        .step.current {
            color: var(--text);
            border-color: rgba(61, 155, 110, 0.45);
            background: rgba(61, 155, 110, 0.12);
            font-weight: 600;
        }
        .panel {
            background: linear-gradient(180deg, rgba(28, 41, 35, 0.95), rgba(22, 32, 28, 0.98));
            border: 1px solid var(--border);
            border-radius: calc(var(--radius) + 2px);
            padding: 1.5rem;
            box-shadow: 0 20px 50px rgba(0, 0, 0, 0.28);
        }
        .panel h2 {
            margin: 0 0 0.35rem;
            font-size: 1.35rem;
            font-weight: 650;
        }
        .panel .lead {
            margin: 0 0 1.25rem;
            color: var(--muted);
            line-height: 1.5;
        }
        .alert {
            border-radius: var(--radius);
            padding: 0.85rem 1rem;
            margin-bottom: 1rem;
            font-size: 0.92rem;
            line-height: 1.45;
        }
        .alert-error { background: rgba(212, 104, 104, 0.12); border: 1px solid rgba(212, 104, 104, 0.35); color: #f0c4c4; }
        .alert-success { background: rgba(76, 175, 130, 0.12); border: 1px solid rgba(76, 175, 130, 0.35); color: #c7ebd8; }
        table {
            width: 100%;
            border-collapse: collapse;
            margin-bottom: 1.25rem;
            font-size: 0.92rem;
        }
        th, td {
            text-align: left;
            padding: 0.7rem 0.55rem;
            border-bottom: 1px solid var(--border);
            vertical-align: top;
        }
        th { color: var(--muted); font-weight: 500; font-size: 0.78rem; text-transform: uppercase; letter-spacing: 0.04em; }
        .badge {
            display: inline-flex;
            align-items: center;
            gap: 0.3rem;
            border-radius: 999px;
            padding: 0.15rem 0.55rem;
            font-size: 0.75rem;
            font-weight: 600;
        }
        .badge-ok { background: rgba(76, 175, 130, 0.15); color: var(--ok); }
        .badge-fail { background: rgba(212, 104, 104, 0.15); color: var(--danger); }
        .badge-optional { background: rgba(212, 162, 76, 0.15); color: var(--warning); }
        .actions {
            display: flex;
            flex-wrap: wrap;
            gap: 0.75rem;
            justify-content: flex-end;
            margin-top: 1.25rem;
        }
        .btn {
            appearance: none;
            border: 1px solid var(--border);
            background: var(--bg-elevated);
            color: var(--text);
            border-radius: 0.7rem;
            padding: 0.65rem 1.05rem;
            font: inherit;
            font-weight: 600;
            cursor: pointer;
        }
        .btn:disabled { opacity: 0.45; cursor: not-allowed; }
        .btn-primary {
            background: linear-gradient(180deg, var(--accent), var(--accent-strong));
            border-color: transparent;
            color: white;
        }
        .btn-ghost { background: transparent; }
        .field { margin-bottom: 1rem; }
        .field label {
            display: block;
            margin-bottom: 0.35rem;
            font-size: 0.85rem;
            color: var(--muted);
        }
        .field input, .field select {
            width: 100%;
            border-radius: 0.7rem;
            border: 1px solid var(--border);
            background: #101914;
            color: var(--text);
            padding: 0.7rem 0.85rem;
            font: inherit;
        }
        .field input:focus, .field select:focus {
            outline: 2px solid rgba(61, 155, 110, 0.35);
            border-color: var(--accent);
        }
        .grid-2 {
            display: grid;
            grid-template-columns: repeat(2, minmax(0, 1fr));
            gap: 0.85rem;
        }
        .hint { color: var(--muted); font-size: 0.8rem; margin-top: 0.3rem; }
        .checklist { margin: 0; padding-left: 1.1rem; color: var(--muted); line-height: 1.7; }
        .code {
            font-family: ui-monospace, SFMono-Regular, Menlo, Consolas, monospace;
            font-size: 0.8rem;
            white-space: pre-wrap;
            background: #0c1210;
            border: 1px solid var(--border);
            border-radius: var(--radius);
            padding: 0.9rem;
            max-height: 240px;
            overflow: auto;
            color: #c9d8cf;
        }
        .toggle {
            display: flex;
            align-items: center;
            gap: 0.55rem;
            color: var(--muted);
            font-size: 0.9rem;
            margin: 0.5rem 0 1rem;
        }
        @media (max-width: 800px) {
            .stepper { grid-template-columns: repeat(4, minmax(0, 1fr)); }
            .grid-2 { grid-template-columns: 1fr; }
        }
    </style>
</head>
<body>
    <div class="shell">
        <div class="brand">
            <div class="brand-mark">HM</div>
            <div>
                <h1>{{ config('saas.brand.name', 'Hotel Management') }}</h1>
                <p>Installation wizard</p>
            </div>
        </div>

        @isset($steps)
            <nav class="stepper" aria-label="Installer progress">
                @foreach ($steps as $step)
                    <div class="step {{ $step['status'] }}">{{ $step['label'] }}</div>
                @endforeach
            </nav>
        @endisset

        <main class="panel">
            @if (session('error'))
                <div class="alert alert-error">{{ session('error') }}</div>
            @endif
            @if (session('success'))
                <div class="alert alert-success">{{ session('success') }}</div>
            @endif
            @if ($errors->any())
                <div class="alert alert-error">
                    <ul style="margin:0;padding-left:1.1rem;">
                        @foreach ($errors->all() as $error)
                            <li>{{ $error }}</li>
                        @endforeach
                    </ul>
                </div>
            @endif

            @yield('content')
        </main>
    </div>
</body>
</html>
