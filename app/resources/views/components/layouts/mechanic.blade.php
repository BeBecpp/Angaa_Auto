@props(['title' => 'Механик'])
<!DOCTYPE html>
<html lang="mn">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1, viewport-fit=cover">
    <meta name="robots" content="noindex, nofollow">
    <title>{{ $title }} — {{ config('angaa.brand.name') }}</title>
    <link rel="icon" type="image/png" href="{{ asset('brand/web/angaa-auto-mark-32.png') }}">
    @vite(['resources/css/app.css', 'resources/js/app.js'])
    @livewireStyles
</head>
<body class="min-h-screen bg-canvas">
    <x-demo-banner />
    <header class="sticky top-0 z-30 border-b border-line bg-surface">
        <div class="mx-auto flex h-14 max-w-3xl items-center justify-between px-4">
            <a href="{{ route('mechanic.index') }}" class="flex items-center gap-2 font-semibold">
                <x-brand-logo variant="mark" class="h-9 w-9" /> Миний ажил
            </a>
            <div class="flex items-center gap-3 text-sm">
                <span class="hidden text-muted sm:inline">{{ auth()->user()->name }}</span>
                @if (auth()->user()->isOfficeStaff())
                    <a href="{{ url('/admin') }}" class="text-primary underline">Админ</a>
                @endif
                <form method="POST" action="{{ route('filament.admin.auth.logout') }}">@csrf<button class="min-h-11 text-muted underline">Гарах</button></form>
            </div>
        </div>
    </header>
    <main class="mx-auto max-w-3xl px-4 py-5">
        {{ $slot }}
    </main>
    @livewireScripts
</body>
</html>
