@php($phone = \App\Support\Settings::contactPhone())
<div class="no-print fixed inset-x-0 bottom-0 z-40 border-t border-line bg-surface p-3 md:hidden" style="padding-bottom: max(0.75rem, env(safe-area-inset-bottom));">
    <div class="flex gap-2">
        @if ($phone)
            <a href="tel:{{ $phone }}" class="btn-secondary flex-1">Залгах</a>
        @endif
        <a href="{{ route('request.create') }}" class="btn-primary flex-[2]">Засварын хүсэлт өгөх</a>
    </div>
</div>
