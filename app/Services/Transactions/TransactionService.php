<?php

declare(strict_types=1);

namespace App\Services\Transactions;

use App\Models\Transaction;
use App\Models\TransactionItem;
use App\Modules\Inventario\Actions\AdjustItemStockAction;
use App\Modules\Inventario\Enums\MovementTypeEnum;
use App\Modules\Inventario\Models\Warehouse;
use App\Services\TenantManager;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

class TransactionService
{
    public function __construct(
        private readonly AdjustItemStockAction $adjustItemStockAction,
        private readonly TenantManager $tenantManager,
    ) {}

    private function getDefaultWarehouse(string $tenantId): Warehouse
    {
        return Warehouse::where('tenant_id', $tenantId)
            ->where('is_default', true)
            ->firstOr(fn () => Warehouse::where('tenant_id', $tenantId)->first())
            ?? throw new \RuntimeException('No warehouse configured for this tenant.');
    }

    private function processTransactionStock(Transaction $transaction, MovementTypeEnum $type): void
    {
        $warehouse = $this->getDefaultWarehouse($transaction->tenant_id);
        $itemsToProcess = $transaction->items->filter(fn (TransactionItem $tItem) => $tItem->item && in_array($tItem->item->item_type, ['spare', 'product', 'raw_material']));

        foreach ($itemsToProcess as $tItem) {
            $this->adjustItemStockAction->execute(
                item: $tItem->item,
                warehouse: $warehouse,
                movementType: $type,
                quantity: (int) $tItem->quantity,
                reason: match ($transaction->type) {
                    'purchase' => "Compra {$transaction->invoice_number}",
                    'sale' => "Venta {$transaction->invoice_number}",
                    default => "Transacción {$transaction->invoice_number}",
                },
                reference: $transaction,
                unitCost: (float) ($tItem->item->cost ?? 0),
                user: $transaction->createdBy,
            );
        }
    }

    public function generateInvoiceNumber(Transaction $transaction): string
    {
        $type = $transaction->type;

        $result = DB::selectOne("
            UPDATE tenants
            SET settings = settings || jsonb_build_object(
                'transactions',
                COALESCE(settings->'transactions', '{}'::jsonb) || jsonb_build_object(
                    '{$type}_counter',
                    COALESCE((settings#>'{transactions,{$type}_counter}')::int, 0) + 1
                )
            )
            WHERE id = ?
            RETURNING settings
        ", [$transaction->tenant_id]);

        if (! $result) {
            throw new \RuntimeException('Tenant no encontrado al generar número de factura.');
        }

        $settings = json_decode($result->settings, true);
        $counter = $settings['transactions']["{$type}_counter"] ?? 1;
        $prefix = $type === 'sale' ? 'FAC' : 'OC';

        return "{$prefix}-".str_pad((string) $counter, 5, '0', STR_PAD_LEFT);
    }

    public function calculateItemTotals(TransactionItem $item): TransactionItem
    {
        $subtotal = $item->quantity * $item->unit_price;
        $item->tax_amount = $subtotal * ($item->tax_rate / 100);
        $item->total_item_amount = $subtotal + $item->tax_amount - ($item->discount_amount ?? 0);

        return $item;
    }

    public function calculateTransactionTotals(Transaction $transaction): Transaction
    {
        $items = $transaction->items;

        $transaction->subtotal = $items->sum(fn (TransactionItem $i) => $i->quantity * $i->unit_price);
        $transaction->total_tax = $items->sum('tax_amount');
        $transaction->total_amount = $items->sum('total_item_amount') - $transaction->total_retentions;

        return $transaction;
    }

    public function recalculateFromItems(Transaction $transaction): Transaction
    {
        $items = $transaction->items;

        foreach ($items as $item) {
            $this->calculateItemTotals($item);
            $item->save();
        }

        $this->calculateTransactionTotals($transaction);
        $transaction->save();

        return $transaction->fresh(['items']);
    }

    public function issue(Transaction $transaction): Transaction
    {
        if (! $transaction->canIssue()) {
            throw new \RuntimeException('Solo transacciones en draft pueden emitirse.');
        }

        return DB::transaction(function () use ($transaction) {
            $transaction->status = 'issued';
            $transaction->cufe = 'CUFE-'.strtoupper((string) Str::uuid());
            $transaction->save();

            $movementType = $transaction->type === 'purchase' ? MovementTypeEnum::Entry : MovementTypeEnum::Exit;

            $this->processTransactionStock($transaction->fresh(['items.item']), $movementType);

            return $transaction->fresh();
        });
    }

    public function cancel(Transaction $transaction): Transaction
    {
        if (! $transaction->canCancel()) {
            throw new \RuntimeException('Solo transacciones emitidas pueden anularse.');
        }

        return DB::transaction(function () use ($transaction) {
            $transaction->status = 'cancelled';
            $transaction->save();

            $reverseType = $transaction->type === 'purchase' ? MovementTypeEnum::Exit : MovementTypeEnum::Entry;

            $this->processTransactionStock($transaction->fresh(['items.item']), $reverseType);

            return $transaction->fresh();
        });
    }

    public function createWithItems(array $transactionData, array $itemsData): Transaction
    {
        return DB::transaction(function () use ($transactionData, $itemsData) {
            $transaction = Transaction::make($transactionData);
            $transaction->tenant_id = $transactionData['tenant_id'];
            $transaction->invoice_number = $this->generateInvoiceNumber($transaction);
            $transaction->save();

            foreach ($itemsData as $itemData) {
                $itemData['transaction_id'] = $transaction->id;
                $itemData['tenant_id'] = $transaction->tenant_id;
                $item = TransactionItem::create($itemData);
                $this->calculateItemTotals($item);
                $item->save();
            }

            return $this->recalculateFromItems($transaction->fresh(['items']));
        });
    }
}
