@props(['plate'])
@if ($plate)
    <span {{ $attributes->merge(['class' => 'inline-flex items-center rounded-[6px] border-2 border-ink bg-surface px-2 py-0.5 font-bold tracking-wide money']) }}>{{ $plate }}</span>
@endif
