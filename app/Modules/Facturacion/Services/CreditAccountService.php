<?php

declare(strict_types=1);

namespace App\Modules\Facturacion\Services;

use App\Models\Contact;
use App\Modules\Facturacion\Exceptions\CreditLimitExceededException;
use App\Modules\Facturacion\Models\CreditAccount;
use App\Modules\Facturacion\Models\CreditTransaction;
use App\Modules\Facturacion\Models\Invoice;
use App\Services\TenantManager;
use Illuminate\Support\Facades\DB;

class CreditAccountService
{
    private function getTenantId(): string
    {
        return app(TenantManager::class)->getCurrentTenantId();
    }

    public function getOrCreateForContact(Contact $contact): CreditAccount
    {
        return CreditAccount::firstOrCreate(
            ['contact_id' => $contact->id, 'tenant_id' => $this->getTenantId()],
            [
                'credit_limit' => 0,
                'current_balance' => 0,
                'payment_terms_days' => 30,
                'is_active' => true,
            ]
        );
    }

    public function charge(Invoice $invoice, float $amount, ?string $dueDate = null): CreditTransaction
    {
        $account = $this->getOrCreateForContact($invoice->contact);

        if (! $account->is_active) {
            throw new \InvalidArgumentException(
                "La cuenta de crédito de {$account->contact->name} está inactiva."
            );
        }

        $effectiveLimit = (float) $account->credit_limit;
        $newBalance = (float) $account->current_balance + $amount;

        if ($effectiveLimit > 0 && $newBalance > $effectiveLimit) {
            throw new CreditLimitExceededException(
                contact: $account->contact,
                currentBalance: (float) $account->current_balance,
                requestedAmount: $amount,
                creditLimit: $effectiveLimit,
            );
        }

        $effectiveDueDate = $dueDate ?? now()->addDays($account->payment_terms_days)->toDateString();

        return DB::transaction(function () use ($account, $invoice, $amount, $effectiveDueDate, $newBalance) {
            $transaction = CreditTransaction::create([
                'credit_account_id' => $account->id,
                'tenant_id' => $this->getTenantId(),
                'type' => 'charge',
                'amount' => $amount,
                'due_date' => $effectiveDueDate,
                'invoice_id' => $invoice->id,
                'reference' => "Factura {$invoice->document_number}",
                'created_by' => auth()->id(),
            ]);

            $account->update(['current_balance' => $newBalance]);

            return $transaction;
        });
    }

    public function payment(
        CreditAccount $account,
        float $amount,
        ?string $invoiceId = null,
        ?string $reference = null,
        ?string $notes = null,
    ): CreditTransaction {
        if ($amount <= 0) {
            throw new \InvalidArgumentException('El monto del pago debe ser mayor a 0.');
        }

        return DB::transaction(function () use ($account, $amount, $invoiceId, $reference, $notes) {
            $transaction = CreditTransaction::create([
                'credit_account_id' => $account->id,
                'tenant_id' => $this->getTenantId(),
                'type' => 'payment',
                'amount' => $amount,
                'invoice_id' => $invoiceId,
                'reference' => $reference,
                'notes' => $notes,
                'ip_address' => request()->ip(),
                'created_by' => auth()->id(),
            ]);

            $newBalance = max(0, (float) $account->current_balance - $amount);
            $account->update(['current_balance' => $newBalance]);

            return $transaction;
        });
    }

    public function reverseCharge(Invoice $invoice): ?CreditTransaction
    {
        $originalCharge = CreditTransaction::where('invoice_id', $invoice->id)
            ->where('type', 'charge')
            ->first();

        if (! $originalCharge) {
            return null;
        }

        $account = $originalCharge->creditAccount;

        return DB::transaction(function () use ($originalCharge, $account, $invoice) {
            $transaction = CreditTransaction::create([
                'credit_account_id' => $account->id,
                'tenant_id' => $this->getTenantId(),
                'type' => 'charge_reverse',
                'amount' => (float) $originalCharge->amount,
                'invoice_id' => $invoice->id,
                'reference' => "Reversa factura {$invoice->document_number}",
                'created_by' => auth()->id(),
            ]);

            $newBalance = max(0, (float) $account->current_balance - (float) $originalCharge->amount);
            $account->update(['current_balance' => $newBalance]);

            return $transaction;
        });
    }

    public function recalculateBalance(CreditAccount $account): float
    {
        $charges = $account->transactions()
            ->whereIn('type', ['charge'])
            ->sum('amount');

        $payments = $account->transactions()
            ->whereIn('type', ['payment', 'charge_reverse'])
            ->sum('amount');

        $newBalance = max(0, (float) $charges - (float) $payments);
        $account->update(['current_balance' => $newBalance]);

        return $newBalance;
    }
}
