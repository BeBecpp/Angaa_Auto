<x-layouts.public title="Үйлчилгээ" description="АНГАА АВТО-гийн үзүүлдэг үйлчилгээ, үнийн төрөл ба барагцаа хугацаа.">
    <section class="container-public py-12">
        <h1 class="text-[34px] md:text-[48px]">Үйлчилгээ</h1>
        <p class="mt-3 max-w-2xl text-muted">Үнэ нь «тогтмол», «эхлэх үнэ» эсвэл «үзлэгийн дараа санал» гэсэн төрөлтэй. Эцсийн үнийг үзлэгийн дараа таны зөвшөөрлөөр тохирно.</p>

        @forelse ($services as $category => $items)
            <h2 class="mt-10 text-2xl">{{ $category ?: 'Бусад' }}</h2>
            <div class="mt-4 grid gap-5 sm:grid-cols-2 lg:grid-cols-3">
                @foreach ($items as $service)
                    <x-service-card :service="$service" />
                @endforeach
            </div>
        @empty
            <x-empty-state class="mt-8" title="Үйлчилгээний жагсаалт хараахан нийтлэгдээгүй байна" :mascot="true">
                Асуудлаа бичээд хүсэлт илгээвэл ажилтан тантай холбогдоно.
                <x-slot:action><a href="{{ route('request.create') }}" class="btn-primary">Засварын хүсэлт өгөх</a></x-slot:action>
            </x-empty-state>
        @endforelse

        <div class="card mt-10 flex flex-col items-start gap-3 md:flex-row md:items-center md:justify-between">
            <div>
                <h2 class="text-xl">Асуудал тодорхойгүй байна уу?</h2>
                <p class="text-muted">«Би сайн мэдэхгүй» гэж сонгоод шинж тэмдгээ бичихэд хангалттай.</p>
            </div>
            <a href="{{ route('request.create') }}" class="btn-primary">Хүсэлт өгөх</a>
        </div>
    </section>
</x-layouts.public>
