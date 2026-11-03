@props(['events'])
<ol class="mt-3 space-y-3 border-l-2 border-line pl-4">
    @forelse ($events as $event)
        <li class="relative">
            <span class="absolute -left-[1.4rem] top-1.5 size-3 rounded-full border-2 border-surface bg-primary" aria-hidden="true"></span>
            <p class="font-semibold">{{ $event->to_status->getLabel() }}</p>
            <p class="text-sm text-muted money">{{ \App\Support\BusinessTime::format($event->occurred_at, 'm/d H:i') }}</p>
        </li>
    @empty
        <li class="text-muted">Мэдээлэл алга.</li>
    @endforelse
</ol>
