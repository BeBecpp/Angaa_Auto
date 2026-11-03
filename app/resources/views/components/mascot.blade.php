@props(['pose' => 'welcome', 'class' => 'w-44'])
{{-- Decorative brand helper; meaning is always carried by surrounding HTML text, so alt is empty. --}}
<img src="{{ asset('brand/web/angaa-auto-mascot-'.$pose.'-240.png') }}"
     srcset="{{ asset('brand/web/angaa-auto-mascot-'.$pose.'-240.png') }} 1x, {{ asset('brand/web/angaa-auto-mascot-'.$pose.'-480.png') }} 2x"
     alt="" width="240" height="295" loading="lazy" {{ $attributes->merge(['class' => 'h-auto '.$class]) }}>
