<?php

namespace App\Http\Controllers;

use App\Models\OpeningRule;
use App\Models\Service;
use App\Models\ServiceRequest;
use App\Support\Settings;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Collection;
use Illuminate\View\View;

class PublicPageController extends Controller
{
    public function home(): View
    {
        return view('public.home', [
            'services' => Service::query()->where('published', true)->where('active', true)->orderBy('sort_order')->limit(6)->get(),
            'hours' => $this->hours(),
            'settings' => Settings::allWithDefaults(),
        ]);
    }

    public function services(): View
    {
        return view('public.services', [
            'services' => Service::query()->where('published', true)->where('active', true)->orderBy('sort_order')->get()->groupBy('category'),
        ]);
    }

    public function service(string $slug): View
    {
        $service = Service::query()->where('slug', $slug)->where('published', true)->where('active', true)->firstOrFail();

        return view('public.service', ['service' => $service]);
    }

    public function contact(): View
    {
        return view('public.contact', [
            'hours' => $this->hours(),
            'settings' => Settings::allWithDefaults(),
            'mapUrl' => Settings::mapUrl(),
            'phone' => Settings::contactPhone(),
        ]);
    }

    public function faq(): View
    {
        return view('public.faq');
    }

    public function privacy(): View
    {
        return view('public.privacy');
    }

    public function terms(): View
    {
        return view('public.terms');
    }

    /**
     * Minimal receipt, visible only to the browser session that submitted the request.
     */
    public function receipt(Request $request, string $reference): View
    {
        $serviceRequest = ServiceRequest::query()
            ->where('reference', $reference)
            ->where('guest_session_hash', hash('sha256', $request->session()->getId()))
            ->first();

        abort_if($serviceRequest === null, 404);

        return view('public.receipt', ['serviceRequest' => $serviceRequest, 'phone' => Settings::contactPhone()]);
    }

    public function robots(): Response
    {
        $lines = app()->isProduction()
            ? ['User-agent: *', 'Disallow: /admin', 'Disallow: /portal', 'Disallow: /mechanic', 'Disallow: /media', 'Sitemap: '.url('/sitemap.xml')]
            : ['User-agent: *', 'Disallow: /'];

        return response(implode("\n", $lines)."\n", 200, ['Content-Type' => 'text/plain']);
    }

    public function sitemap(): Response
    {
        $urls = collect(['home', 'services.index', 'request.create', 'contact', 'faq', 'privacy', 'terms'])->map(fn (string $name): string => route($name));
        $urls = $urls->merge(Service::query()->where('published', true)->where('active', true)->pluck('slug')->map(fn (string $slug): string => route('services.show', $slug)));

        return response()->view('public.sitemap', ['urls' => $urls], 200, ['Content-Type' => 'application/xml']);
    }

    /**
     * @return Collection<int, Collection<int, OpeningRule>>
     */
    private function hours()
    {
        return OpeningRule::query()->where('scope', 'shop')->orderBy('weekday')->orderBy('local_start')->get()->groupBy('weekday');
    }
}
