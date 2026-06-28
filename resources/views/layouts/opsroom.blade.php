<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}" class="h-full">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <script>window.__laravelAuthed = {{ auth()->check() ? 'true' : 'false' }};</script>

    <title>OPS-Room · Planner</title>

    <x-ui-styles />

    @vite(['resources/css/app.css', 'resources/js/app.js'])
    @livewireStyles

    <style>
        html, body { background: #0a0a0a; color: #e5e5e5; height: 100%; margin: 0; padding: 0; overflow: hidden; }
        body { font-family: -apple-system, BlinkMacSystemFont, "Segoe UI", Inter, system-ui, sans-serif; -webkit-font-smoothing: antialiased; }
        .ops-grid-bg {
            background-image:
                linear-gradient(rgba(255,255,255,0.025) 1px, transparent 1px),
                linear-gradient(90deg, rgba(255,255,255,0.025) 1px, transparent 1px);
            background-size: 32px 32px;
        }
        .ops-glow-red    { box-shadow: 0 0 24px -4px rgba(244, 63, 94, 0.55), inset 0 0 0 1px rgba(244, 63, 94, 0.30); }
        .ops-glow-yellow { box-shadow: 0 0 24px -4px rgba(245, 158, 11, 0.45), inset 0 0 0 1px rgba(245, 158, 11, 0.30); }
        .ops-glow-green  { box-shadow: 0 0 24px -4px rgba(16, 185, 129, 0.40), inset 0 0 0 1px rgba(16, 185, 129, 0.25); }
        .ops-glow-gray   { box-shadow: 0 0 16px -4px rgba(115, 115, 115, 0.30), inset 0 0 0 1px rgba(115, 115, 115, 0.25); }
        .ops-pulse-dot   { animation: opsPulse 2s ease-in-out infinite; }
        @keyframes opsPulse {
            0%, 100% { opacity: 1; transform: scale(1); }
            50%      { opacity: 0.5; transform: scale(1.3); }
        }

        /* Marquee — Laufband-Animation für den Activity-Ticker */
        .ops-marquee-track {
            display: inline-flex;
            gap: 2.5rem;
            white-space: nowrap;
            animation: opsMarquee 60s linear infinite;
            will-change: transform;
        }
        .ops-marquee-track:hover { animation-play-state: paused; }
        @keyframes opsMarquee {
            from { transform: translateX(0); }
            to   { transform: translateX(-50%); }
        }
    </style>
</head>

<body class="h-full">
    <div class="h-full w-full ops-grid-bg">
        {{ $slot }}
    </div>

    @livewireScripts
</body>
</html>
