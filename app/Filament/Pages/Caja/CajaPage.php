<?php

declare(strict_types=1);

namespace App\Filament\Pages\Caja;

use App\Modules\Caja\Models\CashMovement;
use App\Modules\Caja\Models\CashShift;
use Filament\Pages\Page;
use Illuminate\Contracts\View\View;

class CajaPage extends Page
{
    protected static string|\BackedEnum|null $navigationIcon = 'heroicon-o-handler';

    protected static string|\UnitEnum|null $navigationGroup = 'Caja';

    protected static ?string $title = 'Caja / Turno';

    protected static ?string $slug = 'caja';

    protected static bool $shouldRegisterNavigation = true;

    protected string $view = 'filament.pages.caja';

    public ?CashShift $shift = null;

    public ?CashShift $lastShift = null;

    public array $filters = [
        'status' => 'open',
    ];

    public string $filterStatus = 'open';

    public function mount(): void
    {
        $this->lastShift = CashShift::where('status', 'closed')
            ->latest('closed_at')
            ->first();
    }

    public function getCardsProperty(): array
    {
        $tenant = Filament::getTenant();

        $currentShift = CashShift::where('tenant_id', $tenant->id)
            ->where('status', 'open')
            ->first();

        $this->shift = $currentShift;

        if ($currentShift) {
            $movements = CashMovement::where('cash_shift_id', $currentShift->id)
                ->latest('created_at')
                ->get();

            $totalSales = $movements->whereIn('type', ['sale', 'sale_tax', 'service'])->sum('amount');
            $totalExpenses = CashMovement::where('cash_shift_id', $currentShift->id)
                ->where('type', 'expense')
                ->sum('amount');

            return [
                'turno_abierto' => $currentShift ? true : false,
                'tiempo_abierto' => $currentShift ? now()->diffForHumans($currentShift->opened_at) : null,
                'monto_inicial' => $currentShift?->initial_amount ?? 0,
                'ventas_totales' => number_format($totalSales ?? 0, 2, ',', '.'),
                'ordenes' => $currentShift?->cashMovements->count() ?? 0,
                'efectivo' => number_format(
                    CashMovement::where('cash_shift_id', $currentShift->id)
                        ->where('payment_method', 'cash')
                        ->sum('amount') ?? 0, 2, ',', '.'
                ),
                'transferencia' => number_format(
                    CashMovement::where('cash_shift_id', $currentShift->id)
                        ->where('payment_method', 'transfer')
                        ->sum('amount') ?? 0, 2, ',', '.'
                ),
                'tarjeta' => number_format(
                    CashMovement::where('cash_shift_id', $currentShift->id)
                        ->where('payment_method', 'card')
                        ->sum('amount') ?? 0, 2, ',', '.'
                ),
                'gastos' => number_format($totalExpenses ?? 0, 2, ',', '.'),
                'neto' => number_format(
                    (($totalSales ?? 0) - ($totalExpenses ?? 0)), 2, ',', '.'
                ),
            ];
        }

        return [
            'turno_abierto' => false,
            'tiempo_abierto' => null,
            'monto_inicial' => 0,
            'ventas_totales' => '0,00',
            'ordenes' => 0,
            'efectivo' => '0,00',
            'transferencia' => '0,00',
            'tarjeta' => '0,00',
            'gastos' => '0,00',
            'neto' => '0,00',
        ];
    }

    public function getTableProperty(): array
    {
        if ($this->shift) {
            $movements = CashMovement::where('cash_shift_id', $this->shift->id)
                ->latest('created_at')
                ->get();

            return [
                'has_shift' => true,
                'movements' => $movements,
            ];
        }

        return ['has_shift' => false];
    }

    public function toggleFilter(string $filter): void
    {
        $this->filterStatus = $filter;
    }

    public function render(): View
    {
        return view('filament.pages.caja', [
            'cards' => $this->cardsProperty(),
            'table' => $this->tableProperty(),
            'lastShift' => $this->lastShift,
        ]);
    }
}
