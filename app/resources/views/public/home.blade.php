<x-layouts.public :description="config('angaa.brand.hero').' Зурагтай тэмдэглэл, ойлгомжтой үнийн санал, дараагийн үйлчилгээний сануулга — Дархан.'">
    {{-- Hero: typography-led, CTA first; no stock imagery --}}
    <section class="border-b border-line bg-surface">
        <div class="container-public grid items-center gap-10 py-12 md:py-20 lg:grid-cols-[1.4fr_1fr]">
            <div>
                <p class="text-sm font-semibold uppercase tracking-wider text-primary">{{ config('angaa.brand.descriptor') }}</p>
                <h1 class="mt-3 text-[34px] md:text-[56px]">{{ config('angaa.brand.hero') }}</h1>
                <p class="mt-5 max-w-xl text-lg text-muted md:text-xl">Зурагтай тэмдэглэл, ойлгомжтой үнийн санал, дараагийн үйлчилгээний сануулга — нэг дор.</p>
                <div class="mt-8 flex flex-col gap-3 sm:flex-row">
                    <a href="{{ route('request.create') }}" class="btn-primary text-lg">Засварын хүсэлт өгөх</a>
                    <a href="{{ route('portal.home') }}" class="btn-secondary text-lg">Миний машины түүх</a>
                </div>
                <p class="mt-4 text-sm text-muted">Хүсэлт илгээх нь цаг баталгаажсан гэсэн үг биш — ажилтан холбогдож цаг, үнийг тохирно.</p>
            </div>
            <div class="hidden justify-center lg:flex">
                <x-mascot pose="welcome" class="w-60" />
            </div>
        </div>
    </section>

    @if ($settings['closure_notice'])
        <div class="container-public mt-6">
            <div class="rounded-[12px] border border-accent bg-accent/15 p-4 font-medium" role="status">{{ $settings['closure_notice'] }}</div>
        </div>
    @endif

    {{-- Three trust proofs --}}
    <section class="container-public py-14 md:py-[88px]" aria-labelledby="proof-heading">
        <h2 id="proof-heading" class="text-[28px] md:text-[36px]">{{ config('angaa.brand.tagline') }}</h2>
        <p class="mt-2 max-w-2xl text-muted">Юу хийсэн бэ? Яагаад хэрэгтэй вэ? Хэд гарах вэ? Дараа хэзээ ирэх вэ? — эдгээрт бодит хариулт өгнө.</p>
        <div class="mt-8 grid gap-5 md:grid-cols-3">
            <div class="card">
                <h3 class="text-xl">Зурагтай тайлбар</h3>
                <p class="mt-2 text-muted">Хүлээн авах үеийн байдал, онош, хийсэн ажлын өмнө/дараах зураг таны машины түүхэнд хадгалагдана.</p>
            </div>
            <div class="card">
                <h3 class="text-xl">Зөвшөөрсөн үнэ</h3>
                <p class="mt-2 text-muted">Ажил, сэлбэг тус бүрээр тайлбарласан үнийн санал. Нэмэлт ажил гарвал дахин таны зөвшөөрлийг авна.</p>
            </div>
            <div class="card">
                <h3 class="text-xl">Дараагийн төлөвлөгөө</h3>
                <p class="mt-2 text-muted">Тухайн машин, үйлчилгээнд тохирсон огноо/км. Та зөвшөөрсөн бол сануулга илгээнэ.</p>
            </div>
        </div>
    </section>

    {{-- How it works --}}
    <section id="how" class="scroll-mt-24 bg-surface py-14 md:py-[88px]" aria-labelledby="how-heading">
        <div class="container-public">
            <h2 id="how-heading" class="text-[28px] md:text-[36px]">Хэрхэн ажилладаг вэ?</h2>
            <ol class="mt-8 grid gap-5 md:grid-cols-3">
                <li class="card">
                    <span class="badge bg-primary-soft text-primary">1-р алхам</span>
                    <h3 class="mt-3 text-xl">Хүсэлт илгээх</h3>
                    <p class="mt-2 text-muted">Асуудлаа бичээд хүсвэл зураг хавсаргана. Бүртгэл шаардлагагүй.</p>
                </li>
                <li class="card">
                    <span class="badge bg-primary-soft text-primary">2-р алхам</span>
                    <h3 class="mt-3 text-xl">Цаг тохирох</h3>
                    <p class="mt-2 text-muted">Ажилтан тантай холбогдож боломжит цагийг баталгаажуулна. Зураг нь онош биш — үзлэгээр тодорхойлно.</p>
                </li>
                <li class="card">
                    <span class="badge bg-primary-soft text-primary">3-р алхам</span>
                    <h3 class="mt-3 text-xl">Үнэ зөвшөөрөөд засуулах</h3>
                    <p class="mt-2 text-muted">Үнийн саналыг харж зөвшөөрнө. Засвар, зураг, дараагийн сануулга таны түүхэнд үлдэнэ.</p>
                </li>
            </ol>
        </div>
    </section>

    {{-- Services --}}
    <section class="container-public py-14 md:py-[88px]" aria-labelledby="services-heading">
        <div class="flex flex-wrap items-end justify-between gap-4">
            <h2 id="services-heading" class="text-[28px] md:text-[36px]">Үйлчилгээ</h2>
            <a href="{{ route('services.index') }}" class="font-semibold text-primary underline underline-offset-4">Бүх үйлчилгээ</a>
        </div>
        @if ($services->isEmpty())
            <x-empty-state class="mt-6" title="Үйлчилгээний жагсаалт удахгүй нэмэгдэнэ">
                Асуудлаа бичээд хүсэлт илгээвэл ажилтан тантай холбогдоно.
                <x-slot:action><a href="{{ route('request.create') }}" class="btn-primary">Засварын хүсэлт өгөх</a></x-slot:action>
            </x-empty-state>
        @else
            <div class="mt-6 grid gap-5 sm:grid-cols-2 lg:grid-cols-3">
                @foreach ($services as $service)
                    <x-service-card :service="$service" />
                @endforeach
            </div>
        @endif
    </section>

    {{-- Owner / team: only supplied facts --}}
    @if ($settings['owner_intro'])
        <section class="container-public pb-14" aria-labelledby="team-heading">
            <div class="card">
                <h2 id="team-heading" class="text-2xl">Бидний тухай</h2>
                <p class="mt-3 whitespace-pre-line text-muted">{{ $settings['owner_intro'] }}</p>
            </div>
        </section>
    @endif

    {{-- Location / hours --}}
    <section class="bg-surface py-14" aria-labelledby="where-heading">
        <div class="container-public grid gap-8 md:grid-cols-2">
            <div>
                <h2 id="where-heading" class="text-[28px]">Байршил ба цаг</h2>
                <p class="mt-3 text-muted">{{ $settings['address'] ?? 'Хаягийг удахгүй нийтэлнэ.' }}</p>
                <a href="{{ route('contact') }}" class="btn-secondary mt-5">Чиглэл, холбоо барих</a>
            </div>
            <x-opening-hours :hours="$hours" />
        </div>
    </section>

    {{-- FAQ teaser --}}
    <section class="container-public py-14" aria-labelledby="faq-heading">
        <h2 id="faq-heading" class="text-[28px]">Түгээмэл асуулт</h2>
        <div class="mt-6 grid gap-4 md:grid-cols-2">
            <div class="card"><h3 class="text-lg">Хүсэлт илгээвэл цаг баталгаажих уу?</h3><p class="mt-2 text-muted">Үгүй. Ажилтан холбогдож боломжит цагийг баталгаажуулна.</p></div>
            <div class="card"><h3 class="text-lg">Зураг илгээвэл онош тавих уу?</h3><p class="mt-2 text-muted">Зураг нь тайлбар — онош биш. Онош машиныг үзсэний дараа гарна.</p></div>
        </div>
        <a href="{{ route('faq') }}" class="mt-6 inline-block font-semibold text-primary underline underline-offset-4">Бүх асуулт</a>
    </section>
</x-layouts.public>
