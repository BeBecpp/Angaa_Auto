@props([
    'title' => null,
    'description' => null,
    'noindex' => false,
    'canonical' => null,
    'stickyActions' => true,
])
@php
    $brand = config('angaa.brand.name');
    $pageTitle = $title ? $title.' — '.$brand : $brand.' — '.config('angaa.brand.descriptor');
    $domain = \App\Support\Settings::get('public_domain');
    $canonicalUrl = $canonical ?? ($domain ? rtrim('https://'.$domain, '/').'/'.ltrim(request()->path(), '/') : url()->current());
@endphp
<!DOCTYPE html>
<html lang="mn">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1, viewport-fit=cover">
    <title>{{ $pageTitle }}</title>
    <meta name="description" content="{{ $description ?? config('angaa.brand.hero').' '.config('angaa.brand.tagline') }}">
    @if ($noindex || ! app()->isProduction())
        <meta name="robots" content="noindex, nofollow">
    @endif
    <link rel="canonical" href="{{ $canonicalUrl }}">
    <link rel="icon" type="image/png" sizes="32x32" href="{{ asset('brand/web/angaa-auto-mark-32.png') }}">
    <link rel="icon" type="image/png" sizes="16x16" href="{{ asset('brand/web/angaa-auto-mark-16.png') }}">
    <link rel="apple-touch-icon" href="{{ asset('brand/web/angaa-auto-mark-180.png') }}">
    <meta name="theme-color" content="#0B5A60">
    <meta property="og:title" content="{{ $pageTitle }}">
    <meta property="og:site_name" content="{{ $brand }}">
    <meta property="og:image" content="{{ asset('brand/web/angaa-auto-logo-primary-640.png') }}">
    @vite(['resources/css/app.css', 'resources/js/app.js'])
    @livewireStyles
    {{ $head ?? '' }}
</head>
<body class="min-h-screen">
    <a href="#main" class="sr-only focus:not-sr-only focus:absolute focus:left-4 focus:top-4 focus:z-50 focus:rounded-[8px] focus:bg-surface focus:px-4 focus:py-2">Үндсэн агуулга руу шилжих</a>
    <x-demo-banner />
    <x-public-header />

    <main id="main" class="{{ $stickyActions ? 'pb-24 md:pb-0' : '' }}">
        {{ $slot }}
    </main>

    <x-public-footer />

    @if ($stickyActions)
        <x-sticky-mobile-actions />
    @endif

    @livewireScripts
</body>
</html>
