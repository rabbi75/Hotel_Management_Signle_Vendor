<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}" dir="{{ config('saas.locales.'.app()->getLocale().'.dir', 'ltr') }}" @class(['dark' => ($appearance ?? 'system') === 'dark'])>
    <head>
        <meta charset="utf-8">
        <meta name="viewport" content="width=device-width, initial-scale=1">
        <meta name="csrf-token" content="{{ csrf_token() }}">
        <meta name="color-scheme" content="light dark">

        {{-- Resolve `system` before first paint. Without this the page renders in
             the light theme for one frame before React hydrates and corrects it. --}}
        <script>
            (function () {
                const appearance = '{{ $appearance ?? 'system' }}';

                if (appearance === 'system') {
                    const prefersDark = window.matchMedia('(prefers-color-scheme: dark)').matches;
                    document.documentElement.classList.toggle('dark', prefersDark);
                }
            })();
        </script>

        {{-- Paint the page background before CSS loads so a reload never flashes white. --}}
        <style>
            html { background-color: oklch(1 0 0); }
            html.dark { background-color: oklch(0.16 0.005 285.9); }
        </style>

        <title inertia>{{ $branding['name'] ?? config('saas.brand.name') }}</title>

        {{-- The operator-uploaded favicon wins outright; with none configured the
             kit's own files stand in, so a fresh install still has a tab icon. --}}
        @if (! empty($branding['favicon']))
            <link rel="icon" href="{{ $branding['favicon'] }}">
            <link rel="apple-touch-icon" href="{{ $branding['icon'] ?? $branding['favicon'] }}">
        @else
            <link rel="icon" href="/favicon.ico" sizes="any">
            <link rel="icon" href="/favicon.svg" type="image/svg+xml">
            <link rel="apple-touch-icon" href="/apple-touch-icon.png">
        @endif

        <link rel="preconnect" href="https://fonts.bunny.net">
        <link href="https://fonts.bunny.net/css?family=inter:400,500,600,700&display=swap" rel="stylesheet">

        {{-- Extension point for modules that contribute to the document head.
             The SEO module pushes its meta, Open Graph and JSON-LD tags here
             from a view composer; an empty stack renders nothing. --}}
        @stack('head')

        @routes
        @viteReactRefresh
        @vite(['resources/css/app.css', 'resources/js/app.tsx', "resources/js/pages/{$page['component']}.tsx"])

        {{-- The operator-chosen sidebar palette, overriding the --sidebar-* tokens
             app.css declares. Assembled from a fixed registry, never from input. --}}
        @if (! empty($sidebarCss))
            <style id="sidebar-palette">{!! $sidebarCss !!}</style>
        @endif

        @inertiaHead
    </head>

    <body class="min-h-screen bg-background font-sans text-foreground antialiased">
        @inertia
    </body>
</html>
