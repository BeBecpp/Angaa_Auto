<?php

namespace App\Providers\Filament;

use App\Filament\NavGroup;
use App\Filament\Pages\Dashboard;
use Filament\FontProviders\LocalFontProvider;
use Filament\Http\Middleware\Authenticate;
use Filament\Http\Middleware\AuthenticateSession;
use Filament\Http\Middleware\DisableBladeIconComponents;
use Filament\Http\Middleware\DispatchServingFilamentEvent;
use Filament\Navigation\NavigationItem;
use Filament\Panel;
use Filament\PanelProvider;
use Filament\Support\Colors\Color;
use Filament\View\PanelsRenderHook;
use Illuminate\Cookie\Middleware\AddQueuedCookiesToResponse;
use Illuminate\Cookie\Middleware\EncryptCookies;
use Illuminate\Foundation\Http\Middleware\PreventRequestForgery;
use Illuminate\Routing\Middleware\SubstituteBindings;
use Illuminate\Session\Middleware\StartSession;
use Illuminate\Support\Facades\Blade;
use Illuminate\Support\Facades\Vite;
use Illuminate\View\Middleware\ShareErrorsFromSession;

class AdminPanelProvider extends PanelProvider
{
    public function panel(Panel $panel): Panel
    {
        return $panel
            ->default()
            ->id('admin')
            ->path('admin')
            ->login()
            ->brandName('АНГАА АВТО')
            ->brandLogo(fn (): string => asset('brand/web/angaa-auto-logo-primary-320.png'))
            ->brandLogoHeight('2.6rem')
            ->favicon(fn (): string => asset('brand/web/angaa-auto-mark-32.png'))
            ->darkMode(false)
            ->font('Noto Sans', url: fn (): string => Vite::asset('resources/css/fonts.css'), provider: LocalFontProvider::class)
            ->colors([
                'primary' => Color::hex('#0B5A60'),
                'warning' => Color::hex('#E6AA42'),
                'success' => Color::hex('#187047'),
                'danger' => Color::hex('#B42318'),
                'gray' => Color::Slate,
                'info' => Color::hex('#0B5A60'),
            ])
            ->navigationGroups(array_map(fn (NavGroup $group): string => $group->getLabel(), NavGroup::cases()))
            ->navigationItems([
                NavigationItem::make('Механикийн дэлгэц')
                    ->url(fn (): string => route('mechanic.index'))
                    ->icon('heroicon-o-device-phone-mobile')
                    ->group(NavGroup::Workshop->getLabel())
                    ->sort(90),
            ])
            ->sidebarCollapsibleOnDesktop()
            ->databaseNotifications()
            ->databaseNotificationsPolling('60s')
            ->renderHook(PanelsRenderHook::BODY_START, fn (): string => Blade::render('<x-demo-banner />'))
            ->renderHook(PanelsRenderHook::HEAD_END, fn (): string => '<meta name="robots" content="noindex, nofollow">')
            ->discoverResources(in: app_path('Filament/Resources'), for: 'App\Filament\Resources')
            ->discoverPages(in: app_path('Filament/Pages'), for: 'App\Filament\Pages')
            ->pages([
                Dashboard::class,
            ])
            ->discoverWidgets(in: app_path('Filament/Widgets'), for: 'App\Filament\Widgets')
            ->widgets([])
            ->middleware([
                EncryptCookies::class,
                AddQueuedCookiesToResponse::class,
                StartSession::class,
                AuthenticateSession::class,
                ShareErrorsFromSession::class,
                PreventRequestForgery::class,
                SubstituteBindings::class,
                DisableBladeIconComponents::class,
                DispatchServingFilamentEvent::class,
            ])
            ->authMiddleware([
                Authenticate::class,
            ]);
    }
}
