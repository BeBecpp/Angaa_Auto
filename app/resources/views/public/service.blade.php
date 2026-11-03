<x-layouts.public :title="$service->name_mn" :description="$service->summary">
    <section class="container-public py-12">
        <nav aria-label="Замын заалт" class="text-sm text-muted"><a href="{{ route('services.index') }}" class="underline">Үйлчилгээ</a> / {{ $service->name_mn }}</nav>
        <div class="mt-4 grid gap-8 lg:grid-cols-[1.5fr_1fr]">
            <div>
                <h1 class="text-[34px] md:text-[48px]">{{ $service->name_mn }}</h1>
                @if ($service->summary)<p class="mt-3 text-lg text-muted">{{ $service->summary }}</p>@endif
                @if ($service->description)<div class="mt-6 whitespace-pre-line">{{ $service->description }}</div>@endif

                <div class="mt-8 grid gap-5 md:grid-cols-2">
                    <div class="card">
                        <h2 class="text-lg">Багтсан зүйлс</h2>
                        <p class="mt-2 whitespace-pre-line text-muted">{{ $service->inclusions ?: 'Үзлэгийн дараа тодорхойлно.' }}</p>
                    </div>
                    <div class="card">
                        <h2 class="text-lg">Багтаагүй зүйлс</h2>
                        <p class="mt-2 whitespace-pre-line text-muted">{{ $service->exclusions ?: 'Сэлбэг болон нэмэлт ажил тусдаа үнийн саналаар.' }}</p>
                    </div>
                </div>
            </div>
            <aside class="card h-fit lg:sticky lg:top-28">
                <dl class="space-y-3">
                    <div><dt class="text-sm text-muted">Үнэ</dt><dd class="text-xl"><x-price-label :service="$service" /></dd></div>
                    <div><dt class="text-sm text-muted">Үнийн төрөл</dt><dd>{{ $service->price_type->getLabel() }}</dd></div>
                    @if ($service->estimated_duration_min)
                        <div><dt class="text-sm text-muted">Барагцаа хугацаа</dt><dd class="money">~{{ $service->estimated_duration_min }} минут</dd></div>
                    @endif
                </dl>
                <a href="{{ route('request.create', ['service' => $service->slug]) }}" class="btn-primary mt-6 w-full">Энэ үйлчилгээгээр хүсэлт өгөх</a>
                <p class="mt-3 text-sm text-muted">Эцсийн үнийг үзлэгийн дараа таны зөвшөөрлөөр тохирно.</p>
            </aside>
        </div>
    </section>
</x-layouts.public>
