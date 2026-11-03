<x-layouts.public title="Миний машины түүх — нэвтрэх" :noindex="true" :stickyActions="false">
    <section class="container-public max-w-md py-12">
        <h1 class="text-[30px]">Миний машины түүх</h1>
        <p class="mt-2 text-muted">Утасны дугаараа нэг удаагийн кодоор баталгаажуулж нэвтэрнэ.</p>
        <div class="mt-6">
            <livewire:portal.portal-login />
        </div>
        <p class="mt-6 text-sm text-muted">Ажилтан уу? <a href="{{ url('/admin/login') }}" class="text-primary underline">Ажилтны нэвтрэх хэсэг</a></p>
    </section>
</x-layouts.public>
