<?php

declare(strict_types=1);

namespace App\Modules\Caja\Services;

use App\Models\User;
use App\Modules\Caja\Exceptions\TurnoCerradoException;
use App\Modules\Caja\Models\CashMovement;
use App\Modules\Caja\Models\CashShift;
use App\Modules\Facturacion\Models\Invoice;
use Illuminate\Support\Facades\DB;

class CashMovementService
{
    public static function recordSale(Invoice $invoice): CashMovement
    {
        return DB::transaction(function () use ($invoice): CashMovement {
            $shift = CashShift::open()->tenantQuery()->first();

            if (! $shift) {
                throw new TurnoCerradoException(
                    'No hay turno de caja abierto. No se pueden registrar ventas.',
                    400
                );
            }

            $paymentMethod = $invoice->payment_method ?? 'cash';

            $movement = CashMovement::create([
                'tenant_id' => $shift->tenant_id,
                'shift_id' => $shift->id,
                'work_order_id' => $invoice->work_order_id ?? null,
                'invoice_id' => $invoice->id,
                'type' => 'sale',
                'payment_method' => $paymentMethod,
                'amount' => $invoice->total,
                'description' => 'Venta: '.($invoice->code ?? 'SIN CODIGO'),
                'created_by' => $invoice->created_by ?? auth()->id() ?? $shift->opened_by,
            ]);

            $shift->addExpectedCash((float) $movement->amount);

            return $movement;
        });
    }

    public static function recordRefund(Invoice $invoice): CashMovement
    {
        return DB::transaction(function () use ($invoice): CashMovement {
            $shift = CashShift::open()->tenantQuery()->first();

            if (! $shift) {
                throw new TurnoCerradoException(
                    'No hay turno de caja abierto. No se pueden registrar reembolsos.',
                    400
                );
            }

            $movement = CashMovement::create([
                'tenant_id' => $shift->tenant_id,
                'shift_id' => $shift->id,
                'type' => 'refund',
                'payment_method' => $invoice->payment_method ?? 'cash',
                'amount' => -$invoice->total,
                'description' => 'Reembolso: '.($invoice->code ?? 'SIN CODIGO'),
                'created_by' => $invoice->created_by ?? auth()->id() ?? $shift->opened_by,
                'work_order_id' => $invoice->work_order_id ?? null,
                'invoice_id' => $invoice->id,
            ]);

            $shift->subtractExpectedCash(abs((float) $movement->amount));

            return $movement;
        });
    }

    public static function openShift(User $user, float $initialAmount): CashShift
    {
        $shift = CashShift::openShift($user, $initialAmount);

        CashMovement::create([
            'tenant_id' => $shift->tenant_id,
            'shift_id' => $shift->id,
            'type' => 'income',
            'payment_method' => 'cash',
            'amount' => $initialAmount,
            'description' => 'Apertura de turno de caja',
            'created_by' => $user->id,
        ]);

        return $shift;
    }

    public static function closeShift(User $user, float $actualCash, string $notes = ''): CashShift
    {
        $shift = CashShift::open()->tenantQuery()->first();

        if (! $shift) {
            throw new TurnoCerradoException(
                'No hay turno de caja abierto para cerrar.',
                400
            );
        }

        $shift->close($user, $actualCash, $notes);

        CashMovement::create([
            'tenant_id' => $shift->tenant_id,
            'shift_id' => $shift->id,
            'type' => 'expense',
            'payment_method' => 'cash',
            'amount' => $actualCash,
            'description' => 'Efectivo contado al cierre: '.$notes,
            'created_by' => $user->id,
        ]);

        return $shift->fresh();
    }
}
