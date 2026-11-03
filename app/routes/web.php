<?php

use App\Http\Controllers\MechanicController;
use App\Http\Controllers\MediaController;
use App\Http\Controllers\PortalController;
use App\Http\Controllers\PublicPageController;
use App\Http\Controllers\StaffPrintController;
use App\Http\Middleware\EnsureCustomer;
use App\Http\Middleware\EnsureStaff;
use App\Http\Middleware\NoIndex;
use Illuminate\Support\Facades\Route;

/*
| Public site (Mongolian slugs)
*/
Route::get('/', [PublicPageController::class, 'home'])->name('home');
Route::get('/uilchilgee', [PublicPageController::class, 'services'])->name('services.index');
Route::get('/uilchilgee/{slug}', [PublicPageController::class, 'service'])->name('services.show');
Route::view('/huselt', 'public.request')->name('request.create');
Route::get('/huselt/{reference}', [PublicPageController::class, 'receipt'])->middleware(NoIndex::class)->name('request.receipt');
Route::get('/holboo-barih', [PublicPageController::class, 'contact'])->name('contact');
Route::get('/tugeemel-asuult', [PublicPageController::class, 'faq'])->name('faq');
Route::get('/nuuts-bodlogo', [PublicPageController::class, 'privacy'])->name('privacy');
Route::get('/uilchilgeenii-nohtsol', [PublicPageController::class, 'terms'])->name('terms');
Route::get('/robots.txt', [PublicPageController::class, 'robots']);
Route::get('/sitemap.xml', [PublicPageController::class, 'sitemap']);

/*
| Customer portal (phone OTP). Staff accounts are rejected here; customers are rejected from /admin.
*/
Route::prefix('portal')->name('portal.')->middleware(NoIndex::class)->group(function (): void {
    Route::view('/nevtreh', 'portal.login')->name('login');

    Route::middleware(EnsureCustomer::class)->group(function (): void {
        Route::get('/', [PortalController::class, 'home'])->name('home');
        Route::get('/mashin/{vehicle}', [PortalController::class, 'vehicle'])->name('vehicle');
        Route::get('/ajil/{workOrder}', [PortalController::class, 'job'])->name('job');
        Route::get('/ajil/{workOrder}/hevleh', [PortalController::class, 'jobPrint'])->name('job.print');
        Route::get('/tsag', [PortalController::class, 'appointments'])->name('appointments');
        Route::view('/tohirgoo', 'portal.preferences')->name('preferences');
        Route::post('/garah', [PortalController::class, 'logout'])->name('logout');
    });
});

/*
| Mechanic phone screens (staff session; owner/advisor may also open them)
*/
Route::prefix('mechanic')->name('mechanic.')->middleware(['auth', EnsureStaff::class, NoIndex::class])->group(function (): void {
    Route::get('/', [MechanicController::class, 'index'])->name('index');
    Route::get('/ajil/{workOrder}', [MechanicController::class, 'show'])->name('job');
});

/*
| Private media: authorization on every request, short private caching, never via storage:link.
*/
Route::get('/media/{media}/{variant}', [MediaController::class, 'show'])
    ->whereIn('variant', ['thumb', 'full'])
    ->middleware(['auth', 'throttle:media', NoIndex::class])
    ->name('media.show');

/*
| Staff print views (browser print-to-PDF)
*/
Route::prefix('staff-print')->name('print.')->middleware(['auth', EnsureStaff::class, NoIndex::class])->group(function (): void {
    Route::get('/work-orders/{workOrder}', [StaffPrintController::class, 'workOrder'])->name('work-order');
    Route::get('/invoices/{invoice}', [StaffPrintController::class, 'invoice'])->name('invoice');
});
