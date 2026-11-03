@props(['variant' => 'primary', 'class' => ''])
{{-- Supplied АНГАА АВТО artwork only. Primary on light surfaces; reversed asset is opaque navy; mark for compact spaces. --}}
@switch($variant)
    @case('mark')
        <img src="{{ asset('brand/web/angaa-auto-mark-96.png') }}" srcset="{{ asset('brand/web/angaa-auto-mark-96.png') }} 1x, {{ asset('brand/web/angaa-auto-mark-192.png') }} 2x"
             alt="АНГАА АВТО" width="48" height="48" {{ $attributes->merge(['class' => 'h-12 w-12 object-contain '.$class]) }}>
        @break
    @case('reversed')
        <img src="{{ asset('brand/web/angaa-auto-logo-reversed-navy-320.png') }}" srcset="{{ asset('brand/web/angaa-auto-logo-reversed-navy-320.png') }} 1x, {{ asset('brand/web/angaa-auto-logo-reversed-navy-640.png') }} 2x"
             alt="АНГАА АВТО" width="240" height="80" {{ $attributes->merge(['class' => 'h-auto w-60 '.$class]) }}>
        @break
    @default
        <img src="{{ asset('brand/web/angaa-auto-logo-primary-320.png') }}" srcset="{{ asset('brand/web/angaa-auto-logo-primary-320.png') }} 1x, {{ asset('brand/web/angaa-auto-logo-primary-640.png') }} 2x"
             alt="АНГАА АВТО" width="240" height="80" {{ $attributes->merge(['class' => 'h-auto w-60 '.$class]) }}>
@endswitch
