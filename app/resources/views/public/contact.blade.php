<x-layouts.public title="Холбоо барих" description="АНГАА АВТО-гийн хаяг, чиглэл, ажлын цаг ба холбоо барих.">
    <section class="container-public py-12">
        <h1 class="text-[34px] md:text-[48px]">Холбоо барих</h1>

        @if ($settings['closure_notice'])
            <div class="mt-6 rounded-[12px] border border-accent bg-accent/15 p-4 font-medium" role="status">{{ $settings['closure_notice'] }}</div>
        @endif

        <div class="mt-8 grid gap-6 md:grid-cols-2">
            <div class="card space-y-4">
                <div>
                    <h2 class="text-lg">Утас</h2>
                    @if ($phone)
                        <a href="tel:{{ $phone }}" class="btn-primary mt-2">{{ \App\Support\Phone::display($phone) }} — залгах</a>
                    @else
                        <p class="mt-1 text-muted">Утасны дугаарыг удахгүй нийтэлнэ. Одоогоор онлайн хүсэлт илгээнэ үү.</p>
                    @endif
                </div>
                <div>
                    <h2 class="text-lg">Хаяг</h2>
                    <p class="mt-1">{{ $settings['address'] ?? 'Хаягийг удахгүй нийтэлнэ.' }}</p>
                    @if ($settings['landmark_directions'])
                        <p class="mt-1 text-muted">{{ $settings['landmark_directions'] }}</p>
                    @endif
                    @if ($mapUrl)
                        <a href="{{ $mapUrl }}" class="btn-secondary mt-3" target="_blank" rel="noopener">Газрын зураг дээр нээх</a>
                    @endif
                </div>
                <div>
                    <h2 class="text-lg">Онлайн хүсэлт</h2>
                    <a href="{{ route('request.create') }}" class="btn-secondary mt-2">Засварын хүсэлт өгөх</a>
                </div>
            </div>
            <x-opening-hours :hours="$hours" />
        </div>
    </section>
</x-layouts.public>
