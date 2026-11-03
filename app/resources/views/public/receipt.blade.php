<x-layouts.public title="Хүсэлт хүлээн авлаа" :noindex="true">
    <section class="container-public max-w-3xl py-12">
        <div class="card grid items-center gap-6 md:grid-cols-[1fr_auto]">
            <div>
                <p class="badge bg-accent/25 text-ink"><span aria-hidden="true">…</span> Хүлээгдэж буй — цаг баталгаажаагүй</p>
                <h1 class="mt-4 text-[30px]">Таны хүсэлтийг хүлээн авлаа</h1>
                <p class="mt-3 text-lg">Бид тантай холбогдож цагийг баталгаажуулна.</p>
                <dl class="mt-6 grid gap-3 sm:grid-cols-2">
                    <div><dt class="text-sm text-muted">Лавлах дугаар</dt><dd class="money text-2xl font-bold">{{ $serviceRequest->reference }}</dd></div>
                    <div><dt class="text-sm text-muted">Илгээсэн</dt><dd class="money">{{ \App\Support\BusinessTime::format($serviceRequest->created_at) }}</dd></div>
                    <div><dt class="text-sm text-muted">Хүсэлтийн төрөл</dt><dd>{{ $serviceRequest->request_type->getLabel() }}</dd></div>
                    <div><dt class="text-sm text-muted">Хавсаргасан зураг</dt><dd>{{ $serviceRequest->media()->count() }}</dd></div>
                </dl>
            </div>
            <x-mascot pose="success" class="mx-auto w-36 md:w-44" />
        </div>

        <div class="card mt-6">
            <h2 class="text-xl">Дараа нь юу болох вэ?</h2>
            <ol class="mt-3 list-decimal space-y-2 pl-5 text-muted">
                <li>Ажилтан таны хүсэлтийг шалгаад утсаар холбогдоно.</li>
                <li>Боломжит цагийг хамт тохирч баталгаажуулна.</li>
                <li>Үзлэгийн дараа үнийн санал гаргаж таны зөвшөөрлийг авна.</li>
            </ol>
            <div class="mt-5 flex flex-wrap gap-3">
                @if ($phone)
                    <a href="tel:{{ $phone }}" class="btn-secondary">Сервис рүү залгах</a>
                @endif
                <a href="{{ route('portal.home') }}" class="btn-secondary">Миний машины түүх</a>
                <a href="{{ route('home') }}" class="btn-secondary">Нүүр хуудас</a>
            </div>
            <p class="mt-4 text-sm text-muted">Лавлах дугаараа хадгална уу. Энэ хуудас зөвхөн энэ төхөөрөмж дээр харагдана; дараа нь мэдээллээ утсаараа нэвтэрч харна.</p>
        </div>
    </section>
</x-layouts.public>
