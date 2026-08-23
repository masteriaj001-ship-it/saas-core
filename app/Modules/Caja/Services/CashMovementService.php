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

            if ($shift->expected_cash === null) {
                $shift->expected_cash = $movement->amount;
            } else {
                $shift->expected_cash = $shift->expected_cash + $movement->amount;
            }
            $shift->save();

            return $movement;
        });
    }

    public static function recordRefund(Invoice $invoice): CashMovement
    {
        return DB::transaction(function () use ($invoice): CashMovement {
            $shift = CashShift::open()->tenantQuery()->first();

            $movement = CashMovement::create([
                'tenant_id' => $invoice->tenant_id,
                'shift_id' => $shift?->id,
                'type' => 'refund',
                'payment_method' => $invoice->payment_method ?? 'cash',
                'amount' => -$invoice->total,
                'description' => 'Reembolso: '.($invoice->code ?? 'SIN CODIGO'),
                'created_by' => $invoice->created_by ?? auth()->id() ?? '',
                'work_order_id' => $invoice->work_order_id ?? null,
                'invoice_id' => $invoice->id,
            ]);

            if ($shift) {
                $shift->actual_cash = $shift->actual_cash ?? 0;
                $shift->difference = $shift->actual_cash - $shift->expected_cash;
                $shift->save();
            }

            return $movement;
        });
    }

    public static function openShift(User $user, float $initialAmount): CashShift
    {
        if (! CashShift::canOpen()) {
            throw new TurnoCerradoException(
                'Ya existe un turno de caja abierto para este tenant.',
                400
            );
        }

        $shift = CashShift::create([
            'tenant_id' => auth()->user()->tenant_id ?? null,
            'opened_by' => $user->id,
            'opened_at' => now(),
            'initial_amount' => $initialAmount,
            'status' => 'open',
            'metadata' => [],
        ]);

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

        if ($shift->status !== 'open') {
            throw new TurnoCerradoException(
                'No se puede cerrar un turno que ya ha sido cerrado.',
                400
            );
        }

        $expectedCash = $shift->expected_cash ?? $shift->initial_amount;
        $difference = $actualCash - $expectedCash;

        $shift->closed_by = $user->id;
        $shift->closed_at = now();
        $shift->actual_cash = $actualCash;
        $shift->difference = $difference;
        $shift->notes = $notes;
        $shift->status = 'closed';
        $shift->save();

        CashMovement::create([
            'tenant_id' => $shift->tenant_id,
            'shift_id' => $shift->id,
            'type' => 'expense',
            'payment_method' => 'cash',
            'amount' => $actualCash,
            'description' => 'Efectivo contado al cierre: '.$notes,
            'created_by' => $user->id,
        ]);

        return $shift;
    }
}
