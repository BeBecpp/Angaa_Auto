@props(['title' => 'Миний машины түүх'])
<x-layouts.public :title="$title" :noindex="true" :stickyActions="false">
    <div class="no-print border-b border-line bg-surface">
        <nav class="container-public flex flex-wrap items-center gap-x-5 gap-y-1 py-2 text-sm" aria-label="Порталын цэс">
            @foreach (['portal.home' => 'Нүүр', 'portal.appointments' => 'Цаг', 'portal.preferences' => 'Тохиргоо'] as $route => $label)
                <a href="{{ route($route) }}" class="flex min-h-11 items-center font-semibold {{ request()->routeIs($route) ? 'text-primary underline underline-offset-8' : 'text-ink' }}">{{ $label }}</a>
            @endforeach
            <form method="POST" action="{{ route('portal.logout') }}" class="ml-auto">
                @csrf
                <button type="submit" class="min-h-11 text-muted underline">Гарах</button>
            </form>
        </nav>
    </div>
    <div class="container-public py-8">
        {{ $slot }}
    </div>
</x-layouts.public>
