@if (app()->environment(['local', 'testing']))
    {{-- Inline styles so the banner also renders inside the Filament panel, which does not load app.css. --}}
    <div class="no-print" role="note"
         style="background:#E6AA42;color:#142632;text-align:center;font-size:13px;font-weight:600;padding:6px 16px;line-height:1.4;">
        ТУРШИЛТЫН ОРЧИН (DEMO) — өгөгдөл зохиомол, SMS бодитоор илгээгдэхгүй, төлбөр бодит биш.
    </div>
@endif
