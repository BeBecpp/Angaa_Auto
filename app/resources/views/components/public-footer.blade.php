@php
    $phone = \App\Support\Settings::contactPhone();
    $address = \App\Support\Settings::get('address');
@endphp
<footer class="no-print mt-16 bg-ink text-white/85">
    <div class="container-public grid gap-10 py-12 md:grid-cols-3">
        <div>
            <x-brand-logo variant="reversed" class="w-56 rounded-[8px]" />
            <p class="mt-4 text-sm">{{ config('angaa.brand.descriptor') }}</p>
            <p class="text-sm italic">{{ config('angaa.brand.tagline') }}</p>
        </div>
        <div>
            <h2 class="text-base font-semibold text-white">Холбоо барих</h2>
            <ul class="mt-3 space-y-2 text-sm">
                <li>Утас: @if ($phone)<a class="underline" href="tel:{{ $phone }}">{{ \App\Support\Phone::display($phone) }}</a>@else <span class="text-white/60">тохируулаагүй байна</span>@endif</li>
                <li>Хаяг: {{ $address ?? 'тохируулаагүй байна' }}</li>
                <li><a class="underline" href="{{ route('contact') }}">Байршил ба цагийн хуваарь</a></li>
            </ul>
        </div>
        <div>
            <h2 class="text-base font-semibold text-white">Холбоос</h2>
            <ul class="mt-3 space-y-2 text-sm">
                <li><a class="underline" href="{{ route('services.index') }}">Үйлчилгээ</a></li>
                <li><a class="underline" href="{{ route('faq') }}">Түгээмэл асуулт</a></li>
                <li><a class="underline" href="{{ route('privacy') }}">Хувийн мэдээллийн бодлого</a></li>
                <li><a class="underline" href="{{ route('terms') }}">Үйлчилгээний нөхцөл</a></li>
                <li><a class="underline" href="{{ route('portal.home') }}">Миний машины түүх</a></li>
            </ul>
        </div>
    </div>
    <div class="border-t border-white/10 py-4 text-center text-xs text-white/60">© {{ now()->year }} {{ config('angaa.brand.name') }}</div>
</footer>
