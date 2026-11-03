@props(['status'])
{{-- Text + symbol, never color alone. Accepts any enum implementing HasLabel/HasColor. --}}
@php
    $color = method_exists($status, 'getColor') ? $status->getColor() : 'gray';
    [$classes, $symbol] = match ($color) {
        'success' => ['bg-success-soft text-success', '✓'],
        'danger' => ['bg-danger-soft text-danger', '!'],
        'warning' => ['bg-accent/25 text-ink', '…'],
        'primary', 'info' => ['bg-primary-soft text-primary', '•'],
        default => ['bg-line/60 text-ink', '–'],
    };
@endphp
<span {{ $attributes->merge(['class' => 'badge '.$classes]) }}><span aria-hidden="true">{{ $symbol }}</span>{{ $status->getLabel() }}</span>
