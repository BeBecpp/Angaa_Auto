@props(['title'])
<!DOCTYPE html>
<html lang="mn">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="robots" content="noindex, nofollow">
    <title>{{ $title }} — {{ config('angaa.brand.name') }}</title>
    @vite(['resources/css/app.css'])
    <style>@page { size: A4; margin: 14mm; } body { background: #fff; }</style>
</head>
<body class="bg-white text-ink">
    <x-demo-banner />
    <div class="mx-auto max-w-[800px] p-6 print:p-0">
        <div class="no-print mb-4 flex gap-3">
            <button type="button" onclick="window.print()" class="btn-primary">Хэвлэх / PDF хадгалах</button>
            <button type="button" onclick="history.length > 1 ? history.back() : window.close()" class="btn-secondary">Буцах</button>
        </div>
        <header class="flex items-start justify-between gap-6 border-b-2 border-ink pb-4">
            <div>
                <img src="{{ asset('brand/web/angaa-auto-logo-primary-640.png') }}" alt="АНГАА АВТО" width="220" height="73" class="h-auto w-[220px]">
                <p class="mt-1 text-sm">{{ config('angaa.brand.descriptor') }}</p>
            </div>
            <div class="text-right text-sm">
                @if ($phone = \App\Support\Settings::contactPhone())<p>Утас: {{ \App\Support\Phone::display($phone) }}</p>@endif
                @if ($address = \App\Support\Settings::get('address'))<p>{{ $address }}</p>@endif
                <p class="money">Хэвлэсэн: {{ \App\Support\BusinessTime::now()->format('Y-m-d H:i') }}</p>
            </div>
        </header>
        {{ $slot }}
    </div>
</body>
</html>
