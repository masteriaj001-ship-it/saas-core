<?php

declare(strict_types=1);

namespace App\Filament\Pages\Caja;

use App\Modules\Caja\Models\CashMovement;
use App\Modules\Caja\Models\CashShift;
use App\Modules\Caja\Services\CashMovementService;
use Filament\Facades\Filament;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Concerns\InteractsWithForms;
use Filament\Forms\Contracts\HasForms;
use Filament\Forms\Form;
use Filament\Notifications\Notification;
use Filament\Pages\Page;

class CajaPage extends Page implements HasForms
{
    use InteractsWithForms;

    protected static string|\BackedEnum|null $navigationIcon = 'heroicon-o-currency-dollar';

    protected static string|\UnitEnum|null $navigationGroup = 'Caja';

    protected static ?string $title = 'Caja / Turno';

    protected static ?string $slug = 'caja';

    protected static bool $shouldRegisterNavigation = true;

    protected string $view = 'filament.pages.caja';

    public ?CashShift $currentShift = null;

    public ?array $cards = null;

    public array $movements = [];

    public ?float $initialAmount = null;

    public ?float $actualCash = null;

    public ?string $closeNotes = null;

    public ?float $difference = null;

    public ?string $expenseDescription = null;

    public ?float $expenseAmount = null;

    public function mount(): void
    {
        $this->loadData();
    }

    public function loadData(): void
    {
        $tenant = Filament::getTenant();

        $this->currentShift = CashShift::where('tenant_id', $tenant->id)
            ->where('status', 'open')
            ->first();

        if ($this->currentShift) {
            $this->loadShiftData();
        } else {
            $this->cards = [
                'turno_abierto' => false,
            ];
            $this->movements = [];
        }
    }

    public function loadShiftData(): void
    {
        if (! $this->currentShift) {
            return;
        }

        $this->currentShift->load(['openedBy', 'closedBy']);

        $movements = CashMovement::where('shift_id', $this->currentShift->id)
            ->latest('created_at')
            ->get();

        $this->movements = $movements->toArray();

        $totalSales = (float) $movements->where('type', 'sale')->sum('amount');
        $totalExpenses = (float) $movements->where('type', 'expense')->sum('amount');
        $totalIncome = (float) $movements->where('type', 'income')->sum('amount');
        $totalRefunds = (float) $movements->where('type', 'refund')->sum('amount');

        $cashSales = (float) $movements->where('type', 'sale')->where('payment_method', 'cash')->sum('amount');
        $cardSales = (float) $movements->where('type', 'sale')->where('payment_method', 'card')->sum('amount');
        $transferSales = (float) $movements->where('type', 'sale')->where('payment_method', 'transfer')->sum('amount');

        $this->cards = [
            'turno_abierto' => true,
            'tiempo_abierto' => now()->diffForHumans($this->currentShift->opened_at),
            'abierto_por' => $this->currentShift->openedBy?->name ?? '---',
            'monto_inicial' => number_format($this->currentShift->initial_amount, 2, ',', '.'),
            'ventas_totales' => number_format($totalSales, 2, ',', '.'),
            'gastos' => number_format($totalExpenses, 2, ',', '.'),
            'efectivo' => number_format($cashSales, 2, ',', '.'),
            'tarjeta' => number_format($cardSales, 2, ',', '.'),
            'transferencia' => number_format($transferSales, 2, ',', '.'),
            'neto' => number_format($totalSales - $totalExpenses, 2, ',', '.'),
            'movimientos_count' => $movements->count(),
        ];
    }

    public function openShift(): void
    {
        $data = $this->form->getState();

        if ($this->currentShift) {
            Notification::make()
                ->title(__('Ya existe un turno abierto'))
                ->danger()
                ->send();

            return;
        }

        try {
            $user = auth()->user();
            CashMovementService::openShift($user, $data['initial_amount']);

            $this->initialAmount = null;
            $this->loadData();

            Notification::make()
                ->title(__('Turno abierto correctamente'))
                ->success()
                ->send();
        } catch (\Exception $e) {
            Notification::make()
                ->title(__('Error al abrir turno'))
                ->body($e->getMessage())
                ->danger()
                ->send();
        }
    }

    public function calculateDifference(): void
    {
        if ($this->actualCash !== null && $this->currentShift) {
            $expected = $this->currentShift->expected_cash ?? $this->currentShift->initial_amount;
            $this->difference = $this->actualCash - $expected;
        }
    }

    public function closeShift(): void
    {
        if (! $this->currentShift) {
            return;
        }

        if ($this->actualCash === null) {
            Notification::make()
                ->title(__('Ingresa el efectivo contado'))
                ->danger()
                ->send();

            return;
        }

        try {
            $user = auth()->user();
            CashMovementService::closeShift($user, $this->actualCash, $this->closeNotes ?? '');

            $this->actualCash = null;
            $this->closeNotes = null;
            $this->difference = null;
            $this->loadData();

            Notification::make()
                ->title(__('Turno cerrado correctamente'))
                ->success()
                ->send();
        } catch (\Exception $e) {
            Notification::make()
                ->title(__('Error al cerrar turno'))
                ->body($e->getMessage())
                ->danger()
                ->send();
        }
    }

    public function recordExpense(): void
    {
        if (! $this->currentShift) {
            return;
        }

        $data = $this->form->getState();

        if (empty($data['expense_description']) || empty($data['expense_amount']) || $data['expense_amount'] <= 0) {
            Notification::make()
                ->title(__('Ingresa descripción y monto válido'))
                ->danger()
                ->send();

            return;
        }

        CashMovement::create([
            'tenant_id' => $this->currentShift->tenant_id,
            'shift_id' => $this->currentShift->id,
            'type' => 'expense',
            'payment_method' => 'cash',
            'amount' => $data['expense_amount'],
            'description' => $data['expense_description'],
            'created_by' => auth()->id(),
        ]);

        $this->currentShift->subtractExpectedCash($data['expense_amount']);

        $this->expenseDescription = null;
        $this->expenseAmount = null;
        $this->loadShiftData();

        Notification::make()
            ->title(__('Gasto registrado'))
            ->success()
            ->send();
    }

    public function form(Form $form): Form
    {
        return $form
            ->schema([
                TextInput::make('initial_amount')
                    ->label(__('Monto Inicial'))
                    ->numeric()
                    ->required()
                    ->minValue(0)
                    ->suffix('$'),
                TextInput::make('actual_cash')
                    ->label(__('Efectivo Contado'))
                    ->numeric()
                    ->required()
                    ->minValue(0)
                    ->suffix('$')
                    ->live(onBlur: true)
                    ->afterStateUpdated(fn ($state) => $this->calculateDifference()),
                Textarea::make('close_notes')
                    ->label(__('Notas de Cierre'))
                    ->rows(2),
                TextInput::make('expense_description')
                    ->label(__('Descripción del Gasto'))
                    ->required(),
                TextInput::make('expense_amount')
                    ->label(__('Monto del Gasto'))
                    ->numeric()
                    ->required()
                    ->minValue(0)
                    ->suffix('$'),
            ]);
    }
}
