@php
    $links = [
        ['route' => 'services.index', 'label' => 'Үйлчилгээ'],
        ['route' => 'home', 'label' => 'Хэрхэн ажилладаг', 'hash' => '#how'],
        ['route' => 'faq', 'label' => 'Түгээмэл асуулт'],
        ['route' => 'contact', 'label' => 'Холбоо барих'],
    ];
@endphp
<header x-data="{ open: false }" class="no-print sticky top-0 z-40 border-b border-line bg-surface/95 backdrop-blur">
    <div class="container-public flex h-16 items-center justify-between gap-4 md:h-20">
        <a href="{{ route('home') }}" class="flex shrink-0 items-center" aria-label="АНГАА АВТО — нүүр хуудас">
            <x-brand-logo variant="mark" class="md:hidden" />
            <x-brand-logo class="hidden w-[240px] md:block lg:w-[260px]" />
        </a>

        <nav class="hidden items-center gap-5 whitespace-nowrap xl:flex" aria-label="Үндсэн цэс">
            @foreach ($links as $link)
                <a href="{{ route($link['route']).($link['hash'] ?? '') }}"
                   class="font-medium text-ink hover:text-primary {{ request()->routeIs($link['route']) && ! isset($link['hash']) ? 'text-primary underline underline-offset-8' : '' }}">{{ $link['label'] }}</a>
            @endforeach
        </nav>

        <div class="flex items-center gap-2">
            <a href="{{ route('portal.home') }}" class="btn-secondary hidden whitespace-nowrap xl:inline-flex">Миний машины түүх</a>
            <a href="{{ route('request.create') }}" class="btn-primary hidden whitespace-nowrap md:inline-flex">Засварын хүсэлт өгөх</a>
            <button type="button" class="btn-secondary xl:hidden" @click="open = !open" :aria-expanded="open.toString()" aria-controls="mobile-menu">
                <span x-text="open ? 'Хаах' : 'Цэс'">Цэс</span>
            </button>
        </div>
    </div>

    <nav id="mobile-menu" x-show="open" x-cloak @keydown.escape.window="open = false" class="border-t border-line bg-surface xl:hidden" aria-label="Гар утасны цэс">
        <ul class="container-public flex flex-col py-2">
            @foreach ($links as $link)
                <li><a href="{{ route($link['route']).($link['hash'] ?? '') }}" @click="open = false" class="flex min-h-12 items-center font-medium">{{ $link['label'] }}</a></li>
            @endforeach
            <li><a href="{{ route('portal.home') }}" class="flex min-h-12 items-center font-medium">Миний машины түүх</a></li>
        </ul>
    </nav>
</header>
