@props(['amount'])
<span {{ $attributes->merge(['class' => 'money whitespace-nowrap']) }}>{{ \App\Support\Money::format($amount) }}</span>
