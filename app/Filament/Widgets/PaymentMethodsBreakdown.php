<?php

declare(strict_types=1);

namespace App\Filament\Widgets;

use App\Enums\PaymentMethodEnum;
use App\Modules\Facturacion\Models\InvoicePayment;
use Filament\Widgets\StatsOverviewWidget as BaseWidget;
use Filament\Widgets\StatsOverviewWidget\Stat;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;

class PaymentMethodsBreakdown extends BaseWidget
{
    protected static ?int $sort = 13;

    protected function getStats(): array
    {
        $startOfMonth = Carbon::now()->startOfMonth();

        $payments = InvoicePayment::query()
            ->where('paid_at', '>=', $startOfMonth)
            ->select('payment_method', DB::raw('sum(amount) as total'), DB::raw('count(*) as count'))
            ->groupBy('payment_method')
            ->get();

        $totalAll = (float) $payments->sum('total');

        $stats = [];
        $colors = [
            'cash' => 'success',
            'card' => 'info',
            'transfer' => 'warning',
            'check' => 'danger',
            'credit' => 'gray',
        ];

        foreach (PaymentMethodEnum::cases() as $method) {
            $row = $payments->where('payment_method', $method->value)->first();
            $total = (float) ($row?->total ?? 0);
            $count = (int) ($row?->count ?? 0);
            $pct = $totalAll > 0 ? round(($total / $totalAll) * 100, 1) : 0;

            $stats[] = Stat::make($method->getLabel(), '$'.number_format($total, 0, ',', '.'))
                ->description($count.' '.__('pagos').' · '.$pct.'%')
                ->descriptionIcon('heroicon-m-banknotes')
                ->color($colors[$method->value] ?? 'gray');
        }

        return $stats;
    }
}
