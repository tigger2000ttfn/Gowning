<?php

namespace App\Providers\Filament;

use Filament\Http\Middleware\Authenticate;
use Filament\Http\Middleware\AuthenticateSession;
use Filament\Http\Middleware\DisableBladeIconComponents;
use Filament\Http\Middleware\DispatchServingFilamentEvent;
use Filament\Panel;
use Filament\PanelProvider;
use Filament\View\PanelsRenderHook;
use Illuminate\Support\Facades\Blade;
use Filament\Support\Colors\Color;
use Illuminate\Cookie\Middleware\AddQueuedCookiesToResponse;
use Illuminate\Cookie\Middleware\EncryptCookies;
use Illuminate\Foundation\Http\Middleware\PreventRequestForgery;
use Illuminate\Routing\Middleware\SubstituteBindings;
use Illuminate\Session\Middleware\StartSession;
use Illuminate\View\Middleware\ShareErrorsFromSession;

class AdminPanelProvider extends PanelProvider
{
    public function panel(Panel $panel): Panel
    {
        return $panel
            ->id('admin')
            ->default()                 // required: marks this as the default panel
            ->login(\App\Filament\Admin\Auth\Login::class)  // custom login (Title Case labels)
            ->passwordReset(requestAction: \App\Filament\Admin\Auth\RequestPasswordReset::class)  // Title Case labels
            ->spa()                     // SPA navigation: persistent sidebar/header, no full reloads
            ->sidebarCollapsibleOnDesktop()  // collapsible sidebar
            ->breadcrumbs(false)  // user dislikes breadcrumbs by titles
            ->databaseNotifications()   // bell icon + notifications in header
            ->databaseNotificationsPolling('60s')
            ->path('admin')
            ->brandName('MATC Gowning Qualification')
            ->brandLogo(fn () => view('filament.brand'))
            ->favicon(asset('images/flying-star.svg'))
            ->viteTheme('resources/css/filament/admin/theme.css')
            ->darkMode(true)            // dark/light toggle available in the user menu
            ->colors([
                // Astellas-inspired palette: magenta/red primary, purple + gold accents,
                // charcoal structural base, teal as a minor accent.
                'primary' => Color::hex('#A4123F'),   // Astellas magenta-red
                'danger' => Color::hex('#C8102E'),    // red
                'warning' => Color::hex('#B8860B'),   // gold
                'success' => Color::hex('#2E7D5B'),   // muted green
                'info' => Color::hex('#6B2C91'),      // purple
                'gray' => Color::hex('#3A3A40'),      // charcoal
                'secondary' => Color::hex('#00838F'), // teal accent
            ])
            ->discoverResources(in: app_path('Filament/Admin/Resources'), for: 'App\Filament\Admin\Resources')
            ->discoverPages(in: app_path('Filament/Admin/Pages'), for: 'App\Filament\Admin\Pages')
            ->pages([])
            ->userMenuItems([
                \Filament\Navigation\MenuItem::make()
                    ->label('Messages')
                    ->icon('heroicon-o-chat-bubble-left-right')
                    ->url(fn () => \App\Filament\Admin\Pages\Messages::getUrl()),
                \Filament\Navigation\MenuItem::make()
                    ->label('Notification Settings')
                    ->icon('heroicon-o-bell-alert')
                    ->url(fn () => \App\Filament\Admin\Pages\NotificationSettings::getUrl()),
            ])
            ->discoverWidgets(in: app_path('Filament/Admin/Widgets'), for: 'App\Filament\Admin\Widgets')
            ->renderHook(
                PanelsRenderHook::HEAD_END,
                fn (): string => Blade::render('@include("filament.head-styles")'),
            )
            ->renderHook(
                PanelsRenderHook::BODY_START,
                fn (): string => Blade::render('@include("filament.login-extras")'),
            )
            ->renderHook(
                PanelsRenderHook::BODY_END,
                fn (): string => Blade::render('@include("filament.session-guard")'),
            )
            ->renderHook(
                PanelsRenderHook::GLOBAL_SEARCH_BEFORE,
                fn (): string => Blade::render('@include("filament.topbar-manage")'),
            )
            ->renderHook(
                PanelsRenderHook::USER_MENU_BEFORE,
                fn (): string => Blade::render('@include("filament.topbar-messages")'),
            )
            ->navigationGroups([
                'Qualifications',
                'Classroom',
                'Review',
            ])
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
                \App\Http\Middleware\EnsurePasswordChanged::class,
            ]);
    }
}
