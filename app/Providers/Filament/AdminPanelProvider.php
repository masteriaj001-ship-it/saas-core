<?php

namespace App\Providers\Filament;

use App\Filament\Pages\Caja\CajaPage;
use App\Http\Middleware\EnsureOnboardingIsCompleted;
use App\Http\Middleware\SetTenantContext;
use App\Http\Middleware\VerifyTenantStatus;
use App\Models\Tenant;
use App\Modules\Talleres\Http\Pages\TallerOnboarding;
use Filament\Auth\MultiFactor\App\AppAuthentication;
use Filament\Auth\MultiFactor\Email\EmailAuthentication;
use Filament\Http\Middleware\Authenticate;
use Filament\Http\Middleware\AuthenticateSession;
use Filament\Http\Middleware\DisableBladeIconComponents;
use Filament\Http\Middleware\DispatchServingFilamentEvent;
use Filament\Pages\Dashboard;
use Filament\Panel;
use Filament\PanelProvider;
use Filament\Support\Colors\Color;
use Filament\Support\Enums\Width;
use Filament\Support\Facades\FilamentView;
use Filament\View\PanelsRenderHook;
use Filament\Widgets\AccountWidget;
use Illuminate\Cookie\Middleware\AddQueuedCookiesToResponse;
use Illuminate\Cookie\Middleware\EncryptCookies;
use Illuminate\Routing\Middleware\SubstituteBindings;
use Illuminate\Session\Middleware\StartSession;
use Illuminate\Support\Facades\Blade;
use Illuminate\View\Middleware\ShareErrorsFromSession;

class AdminPanelProvider extends PanelProvider
{
    public function panel(Panel $panel): Panel
    {
        $panel
            ->default()
            ->id('admin')
            ->path('admin')
            ->darkMode(true, isForced: true)
            ->sidebarCollapsibleOnDesktop()
            ->maxContentWidth(Width::Full)
            ->login()
            ->profile()
            ->passwordReset()
            ->revealablePasswords()
            ->databaseNotifications()
            ->databaseNotificationsPolling('30s')
            ->tenant(Tenant::class, slugAttribute: 'slug')
            ->colors([
                'primary' => Color::Amber,
            ])
            ->discoverResources(in: app_path('Filament/Resources'), for: 'App\Filament\Resources')
            ->discoverPages(in: app_path('Filament/Pages'), for: 'App\Filament\Pages')
            ->pages([
                Dashboard::class,
                TallerOnboarding::class,
                CajaPage::class,
            ])
            ->discoverWidgets(in: app_path('Filament/Widgets'), for: 'App\Filament\Widgets')
            ->widgets([
                AccountWidget::class,
            ])
            ->middleware([
                EncryptCookies::class,
                AddQueuedCookiesToResponse::class,
                StartSession::class,
                AuthenticateSession::class,
                ShareErrorsFromSession::class,
                SubstituteBindings::class,
                DisableBladeIconComponents::class,
                DispatchServingFilamentEvent::class,
            ])
            ->tenantMiddleware([
                SetTenantContext::class,
                EnsureOnboardingIsCompleted::class,
                VerifyTenantStatus::class,
            ], isPersistent: true)
            ->multiFactorAuthentication(
                providers: [
                    AppAuthentication::make(),
                    EmailAuthentication::make(),
                ],
                isRequired: false,
            )
            ->authMiddleware([
                Authenticate::class,
            ]);

        FilamentView::registerRenderHook(
            PanelsRenderHook::AUTH_LOGIN_FORM_AFTER,
            fn (): string => Blade::render('<p class="text-sm text-center text-gray-600 dark:text-gray-400 mt-6">¿No tienes cuenta? <x-filament::link :href="route(\'register\')">Crear cuenta</x-filament::link></p>'),
        );

        FilamentView::registerRenderHook(
            PanelsRenderHook::SCRIPTS_AFTER,
            fn (): string => Blade::render(<<<'HTML'
                <script>
                document.addEventListener('alpine:init', () => {
                    Alpine.data('posKiosk', () => ({
                        init() {
                            this.$nextTick(() => {
                                const searchInput = this.$el.querySelector('.search');
                                if (searchInput) searchInput.focus();
                            });
                        }
                    }));
                });
                </script>
                HTML),
        );

        return $panel;
    }
}
