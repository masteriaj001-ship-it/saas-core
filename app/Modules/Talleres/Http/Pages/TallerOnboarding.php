<?php

declare(strict_types=1);

namespace App\Modules\Talleres\Http\Pages;

use App\Modules\Talleres\Actions\RegisterTenantAction;
use App\Modules\Talleres\Exceptions\TenantRegistrationException;
use App\Services\TenantTemplateSeeder;
use Filament\Forms\Components\Checkbox;
use Filament\Forms\Components\CheckboxList;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Notifications\Notification;
use Filament\Pages\Page;
use Filament\Schemas\Components\Text;
use Filament\Schemas\Components\Utilities\Get;
use Filament\Schemas\Components\Wizard;
use Filament\Schemas\Schema;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\HtmlString;

class TallerOnboarding extends Page
{
    protected static bool $shouldRegisterNavigation = false;

    protected static ?string $slug = 'talleres/onboarding';

    protected string $view = 'talleres::pages.taller-onboarding';

    public ?array $data = [];

    public function mount(): void
    {
        if (Auth::check() && ! empty($this->data)) {
            return;
        }
    }

    public function form(Schema $schema): Schema
    {
        return $schema
            ->schema([
                Wizard::make([
                    $this->identityStep(),
                    $this->workflowStep(),
                    $this->launchStep(),
                ])
                    ->submitAction(new HtmlString('
                        <button
                            type="submit"
                            wire:loading.attr="disabled"
                            wire:target="complete"
                            class="
                                inline-flex items-center justify-center gap-2 px-6 py-2.5
                                text-sm font-medium text-emerald-400
                                bg-emerald-950/40 border border-emerald-500/30
                                rounded-lg transition-all duration-200
                                hover:bg-emerald-950/60 hover:border-emerald-400/50
                                hover:shadow-[0_0_14px_rgba(52,211,153,0.35)]
                                focus:outline-none focus:ring-2 focus:ring-emerald-400/50 focus:ring-offset-2 focus:ring-offset-gray-950
                                disabled:opacity-50 disabled:cursor-not-allowed
                            "
                        >
                            <span wire:loading.remove wire:target="complete">
                                '.__('Ir al Panel →').'
                            </span>
                            <span wire:loading wire:target="complete">
                                '.__('Creando tu taller...').'
                            </span>
                        </button>
                    ')),
            ])
            ->statePath('data');
    }

    public function complete(): void
    {
        try {
            DB::transaction(function () {
                $user = app(RegisterTenantAction::class)->execute([
                    'business_name' => $this->data['taller_name'] ?? '',
                    'name' => $this->data['owner_name'] ?? 'Admin',
                    'email' => $this->data['email'] ?? '',
                    'password' => $this->data['password'] ?? '',
                    'industry' => 'mechanic',
                ]);

                app(TenantTemplateSeeder::class)->seed($user->tenant, 'mechanic');

                $settings = $user->tenant->settings ?? [];
                $settings['taller'] = [
                    'name' => $this->data['taller_name'] ?? '',
                    'address' => $this->data['address'] ?? '',
                    'phone' => $this->data['phone'] ?? '',
                    'specialties' => $this->data['specialties'] ?? [],
                    'billing_method' => $this->data['billing_method'] ?? 'fixed',
                    'hourly_rate' => $this->data['hourly_rate'] ?? null,
                    'auto_create_order' => $this->data['auto_create_order'] ?? true,
                    'notify_on_ready' => $this->data['notify_on_ready'] ?? true,
                    'business_days' => $this->data['business_days'] ?? ['mon', 'tue', 'wed', 'thu', 'fri'],
                ];
                $settings['taller_onboarding_completed'] = true;

                $user->tenant->update(['settings' => $settings, 'onboarding_completed' => true]);

                Auth::login($user);

                Notification::make()
                    ->title(__('¡Taller configurado con éxito!'))
                    ->body(__('Bienvenido a ProyectDashboard. Tu módulo de Talleres Mecánicos está listo.'))
                    ->success()
                    ->send();

                $this->redirect(
                    route('filament.admin.pages.dashboard', ['tenant' => $user->tenant->slug])
                );
            });
        } catch (TenantRegistrationException $e) {
            Notification::make()
                ->title(__('Error de registro'))
                ->body($e->getMessage())
                ->danger()
                ->send();
        } catch (\Throwable $e) {
            Notification::make()
                ->title(__('Error inesperado'))
                ->body(__('No se pudo completar el registro. Intenta nuevamente.'))
                ->danger()
                ->send();
        }
    }

    private function identityStep(): Wizard\Step
    {
        return Wizard\Step::make(__('Tu Cuenta'))
            ->description(__('Datos del propietario'))
            ->icon('heroicon-o-user')
            ->schema([
                TextInput::make('owner_name')
                    ->label(__('Tu nombre'))
                    ->required()
                    ->maxLength(255),
                TextInput::make('email')
                    ->label(__('Correo electrónico'))
                    ->email()
                    ->required()
                    ->maxLength(255)
                    ->unique('users', 'email'),
                TextInput::make('password')
                    ->label(__('Contraseña'))
                    ->password()
                    ->required()
                    ->minLength(8)
                    ->revealable(),
                TextInput::make('taller_name')
                    ->label(__('Nombre del taller'))
                    ->required()
                    ->maxLength(120)
                    ->columnSpanFull(),
                TextInput::make('address')
                    ->label(__('Dirección'))
                    ->maxLength(255)
                    ->columnSpanFull(),
                TextInput::make('phone')
                    ->label(__('Teléfono / WhatsApp'))
                    ->tel()
                    ->maxLength(20),
                CheckboxList::make('specialties')
                    ->label(__('Especialidades'))
                    ->options([
                        'general' => __('Mecánica General'),
                        'motor' => __('Motor'),
                        'transmission' => __('Transmisión'),
                        'electric' => __('Eléctrico / Electrónico'),
                        'diagnostic' => __('Diagnóstico'),
                        'ac' => __('Aire Acondicionado'),
                        'suspension' => __('Suspensión y Dirección'),
                        'brakes' => __('Frenos'),
                    ])
                    ->columns(2),
            ])
            ->columns(2);
    }

    private function workflowStep(): Wizard\Step
    {
        return Wizard\Step::make(__('Workflow'))
            ->description(__('Flujo de trabajo'))
            ->icon('heroicon-o-cog-6-tooth')
            ->schema([
                Select::make('billing_method')
                    ->label(__('Método de cobro'))
                    ->options([
                        'fixed' => __('Por orden fijo'),
                        'hourly' => __('Por hora'),
                        'mixed' => __('Mixto'),
                    ])
                    ->required()
                    ->live(),
                TextInput::make('hourly_rate')
                    ->label(__('Tarifa por hora ($)'))
                    ->numeric()
                    ->prefix('$')
                    ->visible(fn (Get $get) => $get('billing_method') !== 'fixed'),
                Toggle::make('auto_create_order')
                    ->label(__('Generar orden al recibir vehículo'))
                    ->default(true),
                Toggle::make('notify_on_ready')
                    ->label(__('Notificar cliente cuando esté listo'))
                    ->default(true),
                CheckboxList::make('business_days')
                    ->label(__('Días hábiles'))
                    ->options([
                        'mon' => __('Lun'),
                        'tue' => __('Mar'),
                        'wed' => __('Mié'),
                        'thu' => __('Jue'),
                        'fri' => __('Vie'),
                        'sat' => __('Sáb'),
                        'sun' => __('Dom'),
                    ])
                    ->columns(7)
                    ->default(['mon', 'tue', 'wed', 'thu', 'fri']),
            ]);
    }

    private function launchStep(): Wizard\Step
    {
        return Wizard\Step::make(__('Lanzamiento'))
            ->description(__('Revisar y confirmar'))
            ->icon('heroicon-o-rocket-launch')
            ->schema([
                Text::make('summary')
                    ->label('')
                    ->state(fn (Get $get) => $this->buildSummary($get)),
                Checkbox::make('accepted')
                    ->label(__('Confirmo que los datos son correctos'))
                    ->required(),
            ]);
    }

    private function buildSummary(Get $get): string
    {
        $lines = [];

        $lines[] = '📋 '.__('Resumen de configuración');
        $lines[] = '';

        $ownerName = $get('owner_name');
        $email = $get('email');
        if ($ownerName && $email) {
            $lines[] = '👤 '.__('Propietario').': '.e($ownerName).' ('.e($email).')';
        }

        $name = $get('taller_name');
        if ($name) {
            $lines[] = '🏪 '.__('Taller').': '.e($name);
        }

        $specialties = $get('specialties');
        if (! empty($specialties)) {
            $labels = [
                'general' => 'Mecánica General',
                'motor' => 'Motor',
                'transmission' => 'Transmisión',
                'electric' => 'Eléctrico/Electrónico',
                'diagnostic' => 'Diagnóstico',
                'ac' => 'A/C',
                'suspension' => 'Suspensión',
                'brakes' => 'Frenos',
            ];
            $selected = collect($specialties)->map(fn ($s) => $labels[$s] ?? $s)->implode(', ');
            $lines[] = '🔧 '.__('Especialidades').': '.$selected;
        }

        $billing = $get('billing_method');
        if ($billing) {
            $methods = ['fixed' => 'Por orden fijo', 'hourly' => 'Por hora', 'mixed' => 'Mixto'];
            $billingText = $methods[$billing] ?? $billing;
            $rate = $get('hourly_rate');
            if ($rate && $billing !== 'fixed') {
                $billingText .= ' ($'.e((string) $rate).'/h)';
            }
            $lines[] = '💰 '.__('Cobro').': '.$billingText;
        }

        $lines[] = '📅 '.__('Días hábiles').': '.($get('business_days') ? implode(', ', $get('business_days')) : '—');

        return implode("\n", $lines);
    }
}
