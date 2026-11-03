@props(['title', 'mascot' => false])
<div {{ $attributes->merge(['class' => 'card flex flex-col items-center gap-3 text-center']) }}>
    @if ($mascot)
        <x-mascot pose="welcome" class="w-28" />
    @endif
    <h3 class="text-lg font-semibold">{{ $title }}</h3>
    @if ($slot->isNotEmpty())
        <div class="text-muted">{{ $slot }}</div>
    @endif
    {{ $action ?? '' }}
</div>
