<x-layouts.public title="Засварын хүсэлт" description="Бүртгэлгүйгээр засварын хүсэлт илгээх. Ажилтан холбогдож цагийг баталгаажуулна." :stickyActions="false">
    <section class="container-public max-w-3xl py-10">
        <h1 class="text-[30px] md:text-[44px]">Засварын хүсэлт өгөх</h1>
        <p class="mt-2 text-muted">3 алхам · ойролцоогоор 2 минут · бүртгэл шаардлагагүй.</p>
        @if (! \App\Support\Settings::get('public_booking_enabled'))
            <div class="card mt-6" role="status">Онлайн хүсэлт түр хаалттай байна. Утсаар холбогдоно уу.</div>
        @else
            <div class="mt-8">
                <livewire:request-wizard :service="request('service')" />
            </div>
        @endif
    </section>
</x-layouts.public>
