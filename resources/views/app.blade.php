<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}" data-theme="{{ auth()->user()?->theme ?? 'system' }}">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="theme-color" content="#ffffff" media="(prefers-color-scheme: light)">
    <meta name="theme-color" content="#111114" media="(prefers-color-scheme: dark)">
    <link rel="manifest" href="/manifest.webmanifest">
    <link rel="apple-touch-icon" href="/icons/apple-touch-icon.png">
    <meta name="apple-mobile-web-app-capable" content="yes">
    <title inertia>{{ config('app.name') }}</title>
    <script nonce="{{ Vite::cspNonce() }}">
        // Theme before first paint: the signed-in account's choice is authoritative ("system" follows the device);
        // nothing cached on the device can override it, so a shared phone never shows the previous person's theme.
        (function () {
            var el = document.documentElement;
            if (['light', 'dark', 'system'].indexOf(el.dataset.theme) === -1) el.dataset.theme = 'system';
        })();
    </script>
    @viteReactRefresh
    @vite(['resources/css/app.css', 'resources/js/app.tsx'])
    @inertiaHead
</head>
<body class="bg-surface-muted font-sans text-ink antialiased">
    @inertia
</body>
</html>
