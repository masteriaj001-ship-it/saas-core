<?php

declare(strict_types=1);

namespace App\Services\Transactions;

use App\Models\Transaction;
use App\Models\TransactionItem;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

class TransactionService
{
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

        $transaction->status = 'issued';
        $transaction->cufe = 'CUFE-'.strtoupper((string) Str::uuid());
        $transaction->save();

        return $transaction;
    }

    public function cancel(Transaction $transaction): Transaction
    {
        if (! $transaction->canCancel()) {
            throw new \RuntimeException('Solo transacciones emitidas pueden anularse.');
        }

        $transaction->status = 'cancelled';
        $transaction->save();

        return $transaction;
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
