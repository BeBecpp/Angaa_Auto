<x-layouts.portal>
    <div class="mx-auto max-w-2xl">
        <x-empty-state title="Таны утас баталгаажлаа" :mascot="true">
            <p>Таны машины түүхийг харуулахын тулд ажилтан таны бүртгэлийг сервис дээрх мэдээлэлтэй холбох шаардлагатай. Энэ нь бусдын мэдээлэл андуурч харагдахаас сэргийлнэ.</p>
            <p class="mt-2">Дараагийн удаа сервис дээр ирэхдээ эсвэл утсаар холбогдохдоо «порталын бүртгэлээ холбуулъя» гэж хэлнэ үү.</p>
            <x-slot:action>
                <div class="flex flex-wrap justify-center gap-3">
                    @if ($phone)
                        <a href="tel:{{ $phone }}" class="btn-primary">Сервис рүү залгах</a>
                    @endif
                    <a href="{{ route('request.create') }}" class="btn-secondary">Засварын хүсэлт өгөх</a>
                </div>
            </x-slot:action>
        </x-empty-state>
    </div>
</x-layouts.portal>
