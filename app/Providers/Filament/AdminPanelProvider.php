<?php

namespace App\Providers\Filament;

use App\Enums\NavigationGroup;
use App\Filament\Auth\EditProfile;
use App\Filament\Auth\Login;
use App\Http\Middleware\SetLocale;
use Filament\Actions\Action;
use Filament\Http\Middleware\Authenticate;
use Filament\Http\Middleware\AuthenticateSession;
use Filament\Http\Middleware\DisableBladeIconComponents;
use Filament\Http\Middleware\DispatchServingFilamentEvent;
use Filament\Panel;
use Filament\PanelProvider;
use Filament\Support\Colors\Color;
use Filament\Support\Enums\Width;
use Filament\Support\Icons\Heroicon;
use Filament\View\PanelsRenderHook;
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
            ->default()
            ->id('admin')
            ->path('admin')
            ->login(Login::class)
            ->profile(EditProfile::class, isSimple: false)
            ->brandLogo(fn () => view('filament.brand'))
            ->brandLogoHeight('2.75rem')
            ->favicon(asset('favicon.svg'))
            ->font('Hind Siliguri')
            ->colors([
                'primary' => Color::Teal,
                'gray' => Color::Slate,
                'info' => Color::Sky,
                'success' => Color::Emerald,
                'warning' => Color::Amber,
                'danger' => Color::Rose,
            ])
            ->maxContentWidth(Width::Full)
            ->sidebarCollapsibleOnDesktop()
            ->navigationGroups(NavigationGroup::class)
            ->userMenuItems([
                'profile' => fn (Action $action): Action => $action
                    ->label(auth()->user()->name)
                    ->icon(Heroicon::OutlinedUserCircle),
                Action::make('verifyEmail')
                    ->label(__('Verify email'))
                    ->icon(Heroicon::OutlinedExclamationTriangle)
                    ->color('warning')
                    ->url(fn (): string => EditProfile::getUrl())
                    ->visible(fn (): bool => filled(auth()->user()?->email) && ! auth()->user()->hasVerifiedEmail()),
                Action::make('switchLanguage')
                    ->label(fn () => app()->getLocale() === 'bn' ? 'English' : 'বাংলা')
                    ->icon(Heroicon::OutlinedLanguage)
                    ->url(fn () => route('locale.switch', app()->getLocale() === 'bn' ? 'en' : 'bn')),
            ])
            ->renderHook(PanelsRenderHook::GLOBAL_SEARCH_BEFORE, fn () => view('filament.hooks.connection-status'))
            ->discoverResources(in: app_path('Filament/Resources'), for: 'App\Filament\Resources')
            ->discoverPages(in: app_path('Filament/Pages'), for: 'App\Filament\Pages')
            ->discoverWidgets(in: app_path('Filament/Widgets'), for: 'App\Filament\Widgets')
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
                SetLocale::class,
            ])
            ->authMiddleware([
                Authenticate::class,
            ]);
    }
}
