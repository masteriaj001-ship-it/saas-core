<?php

declare(strict_types=1);

namespace App\Filament\Pages;

use App\Services\TenantTemplateSeeder;
use Filament\Facades\Filament;
use Filament\Forms\Components\Placeholder;
use Filament\Forms\Components\Select;
use Filament\Notifications\Notification;
use Filament\Pages\Page;
use Filament\Schemas\Components\Wizard;
use Filament\Schemas\Schema;
use Illuminate\Support\HtmlString;

class Onboarding extends Page
{
    protected static bool $shouldRegisterNavigation = false;

    protected string $view = 'filament.pages.onboarding';

    public ?array $data = [];

    public function mount(): void
    {
        $tenant = Filament::getTenant();

        if ($tenant?->onboarding_completed) {
            redirect()->to(route('filament.admin.pages.dashboard', ['tenant' => $tenant->slug]));
        }
    }

    public function form(Schema $schema): Schema
    {
        return $schema
            ->schema([
                Wizard::make([
                    Wizard\Step::make(__('Perfil de tu Negocio'))
                        ->description(__('Ayúdanos a personalizar tu entorno'))
                        ->icon('heroicon-o-briefcase')
                        ->schema([
                            Select::make('industry')
                                ->label(__('Selecciona tu Sector Comercial'))
                                ->options(
                                    collect(config('industry-defaults.industries'))
                                        ->mapWithKeys(fn ($industry, $key) => [$key => $industry['label']])
                                        ->toArray()
                                )
                                ->required()
                                ->native(false),
                        ]),

                    Wizard\Step::make(__('Inicialización'))
                        ->description(__('Configurar catálogo de arranque'))
                        ->icon('heroicon-o-rocket-launch')
                        ->schema([
                            Placeholder::make('summary')
                                ->label('')
                                ->content(new HtmlString('
                                    <div class="text-center py-6">
                                        <h3 class="text-lg font-bold text-gray-900 dark:text-white">'.__('¡Tu espacio está casi listo!').'</h3>
                                        <p class="text-sm text-gray-500 dark:text-gray-400 mt-2">
                                            '.__('Al hacer clic en finalizar, inyectaremos de forma segura las categorías, activos y configuraciones base optimizadas para tu industria.').'
                                        </p>
                                    </div>
                                ')),
                        ]),
                ])
                    ->submitAction(new HtmlString('
                        <button type="submit" class="fi-btn fi-btn-size-md relative grid-flow-col items-center justify-center font-semibold outline-none transition duration-75 focus-visible:ring-2 rounded-lg bg-primary-600 hover:bg-primary-500 text-white shadow-sm gap-1.5 px-3 py-2 inline-grid dark:bg-primary-500 dark:hover:bg-primary-400">
                            '.__('Finalizar Configuración').'
                        </button>
                    ')),
            ])
            ->statePath('data');
    }

    public function complete(): void
    {
        $tenant = Filament::getTenant();
        $industry = $this->data['industry'] ?? 'general';

        app(TenantTemplateSeeder::class)->seed($tenant, $industry);

        Notification::make()
            ->title(__('¡Espacio Configurado!'))
            ->body(__('Bienvenido a ProyectDashboard. El entorno para :name ha sido inicializado.', ['name' => $tenant->name]))
            ->success()
            ->send();

        redirect()->to(route('filament.admin.pages.dashboard', ['tenant' => $tenant->slug]));
    }
}
