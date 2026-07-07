<!DOCTYPE html>
@php
    // FASE 4D — emit motion data-attributes server-side so the very
    // first paint has the correct reduced/full state with no FOUC.
    // The 3 granular flags are resolved through UserMotionPreference
    // service so they respect the user's resolved preference (e.g. when
    // pref=reduced, all 3 flags resolve to 0). Wrapped in try/catch
    // because some tests deliberately break the default DB connection.
    try {
        $motionProps = app(\App\Services\UserMotionPreference::class)->toInertiaProps(request());
        $motionPref = $motionProps['preference'] ?? 'auto';
        $motionBackdrop = ($motionProps['backdrop'] ?? true) ? '1' : '0';
        $motionSpring = ($motionProps['spring'] ?? true) ? '1' : '0';
        $motionParallax = ($motionProps['parallax'] ?? true) ? '1' : '0';
    } catch (\Throwable $e) {
        $motionPref = 'auto';
        $motionBackdrop = '1';
        $motionSpring = '1';
        $motionParallax = '1';
    }
@endphp
<html lang="pt-BR" class="h-full dark"
      data-motion="{{ $motionPref }}"
      data-motion-backdrop="{{ $motionBackdrop }}"
      data-motion-spring="{{ $motionSpring }}"
      data-motion-parallax="{{ $motionParallax }}">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1, viewport-fit=cover">
    <meta name="theme-color" content="#0b0f1a">
    <title inertia>{{ config('app.name', 'Solar Money') }}</title>

    {{-- Preconnect to fonts CDN for fastest LCP --}}
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link
        href="https://fonts.googleapis.com/css2?family=Manrope:wght@400;500;600;700;800&family=Inter:wght@400;500;600;700&family=JetBrains+Mono:wght@400;500;600&display=swap"
        rel="stylesheet"
    >

    {{-- PWA: manifest + iOS/Android home-screen affordances --}}
    <link rel="manifest" href="/manifest.json">
    <link rel="icon" type="image/png" sizes="32x32" href="/pwa/favicon-32.png">
    <link rel="icon" type="image/png" sizes="16x16" href="/pwa/favicon-16.png">
    <link rel="apple-touch-icon" href="/pwa/apple-touch-icon.png">
    <meta name="apple-mobile-web-app-capable" content="yes">
    <meta name="apple-mobile-web-app-status-bar-style" content="black-translucent">
    <meta name="apple-mobile-web-app-title" content="Solar Money">
    <meta name="mobile-web-app-capable" content="yes">

    @routes
    @vite(['resources/css/app.css', 'resources/js/app.js'])
    @inertiaHead
</head>
<body class="h-full font-body antialiased bg-ink-50 text-ink-900 dark:bg-ink-950 dark:text-ink-50 mesh-canvas relative" style="z-index: 0;">
    <!-- Global Liquid Crystal backdrop — drifts, gives glass something to refract in BOTH modes.
         Trick: -z-10 only works on positioned children. Body is `relative` so mesh sits behind bg.
         We give mesh a high z-index with isolation context so it's visible above body bg. -->
    <div class="fixed inset-0 z-0 pointer-events-none overflow-hidden mesh-canvas" aria-hidden="true">
        <div class="absolute -top-1/4 -right-1/4 w-[60vw] h-[60vw] max-w-[900px] max-h-[900px] rounded-full opacity-70 dark:opacity-60 blur-3xl animate-mesh-drift-a"
             style="background: radial-gradient(circle, rgba(255, 138, 61, 0.75), transparent 60%);"></div>
        <div class="absolute top-1/3 -left-1/4 w-[50vw] h-[50vw] max-w-[800px] max-h-[800px] rounded-full opacity-60 dark:opacity-50 blur-3xl animate-mesh-drift-b"
             style="background: radial-gradient(circle, rgba(124, 58, 237, 0.7), transparent 60%);"></div>
        <div class="absolute -bottom-1/4 right-1/3 w-[40vw] h-[40vw] max-w-[700px] max-h-[700px] rounded-full opacity-50 dark:opacity-40 blur-3xl animate-mesh-drift-a"
             style="animation-delay: -14s; background: radial-gradient(circle, rgba(255, 201, 60, 0.75), transparent 60%);"></div>
        <div class="absolute top-2/3 left-1/4 w-[35vw] h-[35vw] max-w-[600px] max-h-[600px] rounded-full opacity-45 dark:opacity-35 blur-3xl animate-mesh-drift-b"
             style="animation-delay: -7s; background: radial-gradient(circle, rgba(139, 92, 246, 0.65), transparent 60%);"></div>
    </div>
    <div class="relative z-10 min-h-full">
    @inertia
    </div>
</body>
</html>
