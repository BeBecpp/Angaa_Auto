@php($items = $media ?? collect())
@if ($items->isEmpty())
    <p class="text-sm text-gray-500">Зураг алга.</p>
@else
    <ul style="display:grid;grid-template-columns:repeat(auto-fill,minmax(140px,1fr));gap:12px;">
        @foreach ($items as $file)
            <li>
                <a href="{{ route('media.show', [$file, 'full']) }}" target="_blank" rel="noopener">
                    <img src="{{ route('media.show', [$file, 'thumb']) }}" alt="{{ $file->caption ?: $file->category->getLabel() }}" loading="lazy"
                         style="width:100%;aspect-ratio:4/3;object-fit:cover;border-radius:8px;border:1px solid #D9DFDE;">
                </a>
                <p style="font-size:12px;color:#58656C;margin-top:4px;">
                    {{ $file->category->getLabel() }}@if ($file->customer_visible) · <strong>хэрэглэгчид харагдана</strong>@endif
                    @if ($file->caption)<br>{{ $file->caption }}@endif
                    @if ($file->is_demo)<br><strong>DEMO</strong>@endif
                </p>
            </li>
        @endforeach
    </ul>
@endif
