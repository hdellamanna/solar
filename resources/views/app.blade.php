<!DOCTYPE html>
<html lang="pt-BR" class="h-full">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1, viewport-fit=cover">
    <meta name="theme-color" content="#FF8A3D">
    <title inertia>{{ config('app.name', 'Solar') }}</title>
    <link rel="preconnect" href="https://fonts.bunny.net">
    <link href="https://fonts.bunny.net/css?family=inter:400,500,600,700&display=swap" rel="stylesheet" />

    {{-- PWA: manifest + iOS/Android home-screen affordances --}}
    <link rel="manifest" href="/manifest.json">
    <link rel="icon" type="image/png" sizes="32x32" href="/pwa/favicon-32.png">
    <link rel="icon" type="image/png" sizes="16x16" href="/pwa/favicon-16.png">
    <link rel="apple-touch-icon" href="/pwa/apple-touch-icon.png">
    <meta name="apple-mobile-web-app-capable" content="yes">
    <meta name="apple-mobile-web-app-status-bar-style" content="default">
    <meta name="apple-mobile-web-app-title" content="Solar">
    <meta name="mobile-web-app-capable" content="yes">

    @routes
    @vite(['resources/css/app.css', 'resources/js/app.js'])
    @inertiaHead
</head>
<body class="h-full antialiased">
    @inertia
</body>
</html>
