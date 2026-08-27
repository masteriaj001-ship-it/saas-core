# FEATURE SPEC — Phase 2: Cartera / Crédito (Fiado)

> Estado: **COMPLETADO** — 2026-08-28
> Tests: 564/564 green (17 nuevos)

---

## 1. CONTEXTO DEL PROYECTO

### 1.1 Schema Real de `invoices` (verificado)

```sql
-- Ya existe en la base de datos
invoices:
  id UUID PK
  tenant_id UUID FK → tenants (RLS activo)
  work_order_id UUID FK nullable
  contact_id UUID FK nullable
  document_type VARCHAR(20)  -- 'invoice', 'credit_note', 'pos'
  prefix VARCHAR(10)
  sequence INTEGER
  document_number VARCHAR(30)
  status VARCHAR(20) DEFAULT 'draft'  -- draft, issued, paid, confirmed, cancelled
  payment_method VARCHAR(20) DEFAULT 'cash'  -- cash, card, transfer, check, credit
  issued_at TIMESTAMPTZ
  due_at TIMESTAMPTZ
  subtotal DECIMAL(12,2)
  discount_total DECIMAL(12,2)
  tax_total DECIMAL(12,2)
  total DECIMAL(12,2)
  grand_total DECIMAL(12,2)
  notes TEXT
  deleted_at TIMESTAMPTZ
  created_at/updated_at TIMESTAMPTZ
```

### 1.2 Enums Existentes (NO crear nuevos)

```php
// app/Enums/PaymentMethodEnum.php — YA TIENE Credit
PaymentMethodEnum::Credit = 'credit'  // ← USAR ESTE

// app/Enums/InvoiceStatusEnum.php
InvoiceStatusEnum::Draft, Issued, Paid, Cancelled
// NOTA: 'Confirmed' existe en el enum pero no tiene label (posible bug)
```

### 1.3 Observer No Existe

No hay `InvoiceObserver` en el proyecto. Se crea desde cero.

### 1.4 ServiceProvider Existente

`FacturacionServiceProvider` registra `InvoiceCodeGenerator` como singleton. Aquí se registra el observer.

---

## 2. DECISIONES ARQUITECTÓNICAS

| Decisión | Valor | Justificación |
|----------|-------|---------------|
| **Trigger** | `InvoiceObserver::updated()` cuando `status → issued/paid` Y `payment_method = credit` | No disparar en draft (la factura puede editarse). Solo al emitir o pagar. |
| **Ledger** | `credit_transactions` inmutable (sin update, sin delete) | Contabilidad: cada línea es un hecho histórico. Pagos parciales = múltiples entradas. |
| **Balance** | `current_balance` positivo = deuda, negativo = saldo a favor | Convención contable estándar. Permite anticipos. |
| **Crédito disponible** | `available_credit` = GENERATED STORED (`credit_limit - GREATEST(current_balance, 0)`) | PostgreSQL calcula. Sin race conditions. Solo para saldo positivo. |
| **Vencimiento** | `due_date` en el cargo (charge), no en la cuenta | Cada venta tiene su propio vencimiento (30, 60, 90 días según acuerdo). |
| **Cancelación** | `InvoiceObserver::updated()` detecta `status → cancelled` con `payment_method = credit` | Reversa automática: crea transacción `type=charge_reverse`, actualiza balance. |
| **Crédito negativo** | Permitido (saldo a favor) | Cliente puede hacer anticipos. `available_credit` crece. |
| **Multi-tenancy** | `tenant_id` + RLS + BelongsToTenant en todas las tablas nuevas | Doble capa de aislamiento (RLS + Eloquent scope). |
| **Auditoría** | `created_by` en credit_accounts, `ip_address`/`notes` en credit_transactions | Trazabilidad de quién registró qué. |

---

## 3. SCHEMA

### 3.1 Tabla Nueva: `credit_accounts`

```sql
CREATE TABLE credit_accounts (
    id UUID PRIMARY KEY DEFAULT gen_random_uuid(),
    tenant_id UUID NOT NULL REFERENCES tenants(id) ON DELETE CASCADE,
    contact_id UUID NOT NULL REFERENCES contacts(id) ON DELETE RESTRICT,
    credit_limit DECIMAL(12,2) DEFAULT 0 CHECK (credit_limit >= 0),
    current_balance DECIMAL(12,2) DEFAULT 0,
    available_credit DECIMAL(12,2) GENERATED ALWAYS AS (
        credit_limit - GREATEST(current_balance, 0)
    ) STORED,
    payment_terms_days INT DEFAULT 30,
    is_active BOOLEAN DEFAULT true,
    notes TEXT,
    metadata JSONB DEFAULT '{}',
    created_by UUID REFERENCES users(id) ON DELETE SET NULL,
    created_at TIMESTAMPTZ DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMPTZ DEFAULT CURRENT_TIMESTAMP,
    CONSTRAINT unique_credit_account_per_tenant_contact UNIQUE (tenant_id, contact_id)
);

CREATE INDEX idx_credit_accounts_tenant ON credit_accounts(tenant_id);
CREATE INDEX idx_credit_accounts_contact ON credit_accounts(contact_id);
CREATE INDEX idx_credit_accounts_active ON credit_accounts(is_active) WHERE is_active = true;
```

**RLS:**
```sql
ALTER TABLE credit_accounts ENABLE ROW LEVEL SECURITY;
ALTER TABLE credit_accounts FORCE ROW LEVEL SECURITY;

CREATE POLICY credit_accounts_tenant_isolation ON credit_accounts
    FOR ALL USING (tenant_id = public.current_tenant_id());
```

**Notas:**
- `available_credit` es GENERATED STORED — no se puede INSERT/UPDATE directamente. Solo se actualiza vía `current_balance`.
- `current_balance` se calcula desde `SUM(credit_transactions.amount)` donde tipo=charge es positivo, tipo=payment es negativo. Se mantiene como cache para queries rápidas.
- Un contacto solo puede tener UNA cuenta de crédito por tenant.

### 3.2 Tabla Nueva: `credit_transactions`

```sql
CREATE TABLE credit_transactions (
    id UUID PRIMARY KEY DEFAULT gen_random_uuid(),
    tenant_id UUID NOT NULL REFERENCES tenants(id) ON DELETE CASCADE,
    credit_account_id UUID NOT NULL REFERENCES credit_accounts(id) ON DELETE RESTRICT,
    type VARCHAR(30) NOT NULL CHECK (type IN ('charge', 'payment', 'charge_reverse', 'payment_reversal')),
    amount DECIMAL(12,2) NOT NULL CHECK (amount > 0),
    due_date DATE,
    paid_at TIMESTAMPTZ,
    invoice_id UUID REFERENCES invoices(id) ON DELETE SET NULL,
    reference VARCHAR(255),
    notes TEXT,
    ip_address INET,
    metadata JSONB DEFAULT '{}',
    created_by UUID REFERENCES users(id) ON DELETE SET NULL,
    created_at TIMESTAMPTZ DEFAULT CURRENT_TIMESTAMP
);

CREATE INDEX idx_credit_txn_tenant ON credit_transactions(tenant_id);
CREATE INDEX idx_credit_txn_account ON credit_transactions(credit_account_id);
CREATE INDEX idx_credit_txn_type ON credit_transactions(type);
CREATE INDEX idx_credit_txn_invoice ON credit_transactions(invoice_id);
CREATE INDEX idx_credit_txn_due_date ON credit_transactions(due_date) WHERE paid_at IS NULL;
CREATE INDEX idx_credit_txn_created ON credit_transactions(created_at);
```

**RLS:**
```sql
ALTER TABLE credit_transactions ENABLE ROW LEVEL SECURITY;
ALTER TABLE credit_transactions FORCE ROW LEVEL SECURITY;

CREATE POLICY credit_transactions_tenant_isolation ON credit_transactions
    FOR ALL USING (tenant_id = public.current_tenant_id());
```

**Notas:**
- INMUTABLE: No hay UPDATE ni DELETE en esta tabla. Cada transacción es un hecho histórico.
- `amount` siempre positivo. El `type` determina si suma o resta al balance.
- `due_date` solo aplica para `type=charge`. Para pagos es null.
- `paid_at` solo aplica para `type=charge` cuando se paga (se actualiza con el pago). **EXCEPCIÓN a la inmutabilidad**: este campo se actualiza cuando se registra el pago. Alternativa: no actualizar, y buscar pagos por `invoice_id` o `credit_account_id` + `created_at`. **DECISIÓN: NO actualizar paid_at en el charge. En su lugar, el paid_at se registra en la transacción de tipo 'payment'. El aging busca charges sin pago asociado.**
- `ip_address` para auditoría de quién registró el pago.

### 3.3 Modificación: `invoices`

```sql
ALTER TABLE invoices ADD COLUMN credit_account_id UUID REFERENCES credit_accounts(id) ON DELETE SET NULL;
CREATE INDEX idx_invoices_credit_account ON invoices(credit_account_id);
```

---

## 4. MODELOS

### 4.1 CreditAccount

```php
<?php

declare(strict_types=1);

namespace App\Modules\Facturacion\Models;

use App\Models\Contact;
use App\Models\Concerns\BelongsToTenant;
use App\Models\TenantModel;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class CreditAccount extends TenantModel
{
    use BelongsToTenant;

    protected $fillable = [
        'contact_id',
        'credit_limit',
        'current_balance',
        'payment_terms_days',
        'is_active',
        'notes',
        'metadata',
        'created_by',
    ];

    protected function casts(): array
    {
        return array_merge(parent::casts(), [
            'credit_limit' => 'decimal:2',
            'current_balance' => 'decimal:2',
            'is_active' => 'boolean',
            'metadata' => 'array',
        ]);
    }

    // --- Relationships ---

    public function contact(): BelongsTo
    {
        return $this->belongsTo(Contact::class);
    }

    public function transactions(): HasMany
    {
        return $this->hasMany(CreditTransaction::class);
    }

    public function charges(): HasMany
    {
        return $this->transactions()->where('type', 'charge');
    }

    public function payments(): HasMany
    {
        return $this->transactions()->where('type', 'payment');
    }

    public function overdueCharges(): HasMany
    {
        return $this->charges()
            ->whereNull('paid_at')
            ->where('due_date', '<', now());
    }

    public function invoices(): HasMany
    {
        return $this->hasMany(Invoice::class);
    }

    // --- Accessors ---

    public function getIsOverdueAttribute(): bool
    {
        return $this->current_balance > 0
            && $this->overdueCharges()->exists();
    }

    public function getOverdueAmountAttribute(): float
    {
        return (float) $this->overdueCharges()->sum('amount');
    }
}
```

### 4.2 CreditTransaction

```php
<?php

declare(strict_types=1);

namespace App\Modules\Facturacion\Models;

use App\Models\Concerns\BelongsToTenant;
use App\Models\TenantModel;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class CreditTransaction extends TenantModel
{
    use BelongsToTenant;

    protected $fillable = [
        'credit_account_id',
        'type',
        'amount',
        'due_date',
        'paid_at',
        'invoice_id',
        'reference',
        'notes',
        'ip_address',
        'metadata',
        'created_by',
    ];

    protected function casts(): array
    {
        return array_merge(parent::casts(), [
            'amount' => 'decimal:2',
            'due_date' => 'date',
            'paid_at' => 'datetime',
            'metadata' => 'array',
        ]);
    }

    // --- Relationships ---

    public function creditAccount(): BelongsTo
    {
        return $this->belongsTo(CreditAccount::class);
    }

    public function invoice(): BelongsTo
    {
        return $this->belongsTo(Invoice::class);
    }

    // --- Accessors ---

    public function getDaysOverdueAttribute(): ?int
    {
        if (! $this->due_date || $this->paid_at) {
            return null;
        }

        return max(0, (int) $this->due_date->diffInDays(now()));
    }

    public function getIsOverdueAttribute(): bool
    {
        return ! $this->paid_at
            && $this->due_date
            && $this->due_date->isPast();
    }
}
```

---

## 5. SERVICIOS

### 5.1 CreditAccountService

```php
<?php

declare(strict_types=1);

namespace App\Modules\Facturacion\Services;

use App\Models\Contact;
use App\Modules\Facturacion\Models\CreditAccount;
use App\Modules\Facturacion\Models\CreditTransaction;
use App\Modules\Facturacion\Models\Invoice;
use Illuminate\Support\Facades\DB;

class CreditAccountService
{
    /**
     * Obtiene o crea la cuenta de crédito de un contacto.
     */
    public function getOrCreateForContact(Contact $contact): CreditAccount
    {
        return CreditAccount::firstOrCreate(
            ['contact_id' => $contact->id, 'tenant_id' => tenant('id')],
            [
                'credit_limit' => 0,
                'current_balance' => 0,
                'payment_terms_days' => 30,
                'is_active' => true,
            ]
        );
    }

    /**
     * Registra un cargo (venta a crédito).
     * Llamado desde InvoiceObserver cuando payment_method = credit.
     */
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
            throw new \App\Modules\Facturacion\Exceptions\CreditLimitExceededException(
                contact: $account->contact,
                currentBalance: (float) $account->current_balance,
                requestedAmount: $amount,
                creditLimit: $effectiveLimit,
            );
        }

        $effectiveDueDate = $dueDate ?? now()->addDays($account->payment_terms_days)->toDateString();

        return DB::transaction(function () use ($account, $invoice, $amount, $effectiveDueDate) {
            $transaction = CreditTransaction::create([
                'credit_account_id' => $account->id,
                'tenant_id' => tenant('id'),
                'type' => 'charge',
                'amount' => $amount,
                'due_date' => $effectiveDueDate,
                'invoice_id' => $invoice->id,
                "reference" => "Factura {$invoice->document_number}",
                'created_by' => auth()->id(),
            ]);

            $account->update(['current_balance' => $newBalance]);

            return $transaction;
        });
    }

    /**
     * Registra un abono del cliente.
     */
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
                'tenant_id' => tenant('id'),
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

    /**
     * Reversa un cargo cuando la factura se cancela.
     */
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
                'tenant_id' => tenant('id'),
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

    /**
     * Recalcula el balance desde las transacciones (util para repair/drift).
     */
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
```

### 5.2 CreditReportService

```php
<?php

declare(strict_types=1);

namespace App\Modules\Facturacion\Services;

use App\Modules\Facturacion\Models\CreditAccount;
use Carbon\Carbon;

class CreditReportService
{
    /**
     * Estado de cuenta de un cliente.
     */
    public function getStatement(CreditAccount $account, ?Carbon $asOf = null): array
    {
        $asOf = $asOf ?? now();

        $transactions = $account->transactions()
            ->whereDate('created_at', '<=', $asOf)
            ->orderBy('created_at', 'asc')
            ->get();

        $charges = $transactions->where('type', 'charge');
        $payments = $transactions->whereIn('type', ['payment', 'charge_reverse']);

        // Vencidos: cargos sin pago asociado cuya due_date ya pasó
        $overdue = $charges->filter(
            fn ($t) => $t->due_date && $t->due_date->lt($asOf) && $this->isUnpaid($t, $transactions)
        );

        return [
            'contact' => $account->contact->name,
            'credit_limit' => (float) $account->credit_limit,
            'current_balance' => (float) $account->current_balance,
            'available_credit' => (float) max(0, (float) $account->credit_limit - (float) $account->current_balance),
            'total_charges' => (float) $charges->sum('amount'),
            'total_payments' => (float) $payments->sum('amount'),
            'overdue_amount' => (float) $overdue->sum('amount'),
            'overdue_count' => $overdue->count(),
            'transactions' => $transactions,
            'as_of' => $asOf->toDateTimeString(),
        ];
    }

    /**
     * Verifica si un cargo está pagado (tiene pago asociado por invoice_id o por monto).
     */
    private function isUnpaid($charge, $transactions): bool
    {
        // Opción 1: Buscar pago con mismo invoice_id
        $paymentByInvoice = $transactions->first(
            fn ($t) => $t->type === 'payment' && $t->invoice_id === $charge->invoice_id
        );

        if ($paymentByInvoice) {
            return false;
        }

        // Opción 2: Verificar si hay charge_reverse para este cargo
        $reversal = $transactions->first(
            fn ($t) => $t->type === 'charge_reverse' && $t->invoice_id === $charge->invoice_id
        );

        return ! $reversal;
    }

    /**
     * Clientes con saldo vencido (para cobranza diaria).
     */
    public function getOverdueAccounts(?int $daysOverdue = null)
    {
        $query = CreditAccount::where('current_balance', '>', 0)
            ->whereHas('transactions', function ($q) use ($daysOverdue) {
                $q->where('type', 'charge')
                    ->whereNull('invoice_id') // cargos manuales sin factura
                    ->orWhere(function ($q2) use ($daysOverdue) {
                        $q2->where('type', 'charge')
                            ->where('due_date', '<', now());
                        if ($daysOverdue) {
                            $q2->where('due_date', '<', now()->subDays($daysOverdue));
                        }
                    });
            })
            ->with('contact');

        return $query->get();
    }

    /**
     * Aging report: 0-30, 31-60, 61-90, 90+ días vencidos.
     */
    public function getAgingReport(): array
    {
        $accounts = CreditAccount::where('current_balance', '>', 0)
            ->with(['transactions' => function ($q) {
                $q->where('type', 'charge');
            }])
            ->get();

        $report = [];

        foreach ($accounts as $account) {
            $buckets = [
                'current' => 0,
                '1_30' => 0,
                '31_60' => 0,
                '61_90' => 0,
                '90_plus' => 0,
            ];

            foreach ($account->transactions as $charge) {
                if (! $charge->due_date || $charge->due_date->isFuture()) {
                    $buckets['current'] += (float) $charge->amount;
                    continue;
                }

                $daysOverdue = (int) $charge->due_date->diffInDays(now());

                match (true) {
                    $daysOverdue <= 30 => $buckets['1_30'] += (float) $charge->amount,
                    $daysOverdue <= 60 => $buckets['31_60'] += (float) $charge->amount,
                    $daysOverdue <= 90 => $buckets['61_90'] += (float) $charge->amount,
                    default => $buckets['90_plus'] += (float) $charge->amount,
                };
            }

            // Solo incluir si tiene algo vencido
            if (array_sum($buckets) > 0) {
                $report[] = [
                    'contact' => $account->contact->name,
                    'phone' => $account->contact->phone ?? null,
                    'total_balance' => (float) $account->current_balance,
                    'buckets' => $buckets,
                ];
            }
        }

        return $report;
    }
}
```

---

## 6. OBSERVER

### 6.1 InvoiceObserver

```php
<?php

declare(strict_types=1);

namespace App\Modules\Facturacion\Observers;

use App\Enums\InvoiceStatusEnum;
use App\Enums\PaymentMethodEnum;
use App\Modules\Facturacion\Models\Invoice;
use App\Modules\Facturacion\Services\CreditAccountService;

class InvoiceObserver
{
    public function __construct(
        private CreditAccountService $creditService,
    ) {}

    /**
     * Detecta:
     * 1. status → issued/paid CON payment_method = credit → crear cargo
     * 2. status → cancelled CON payment_method = credit → reversar cargo
     */
    public function updated(Invoice $invoice): void
    {
        if (! $invoice->contact_id) {
            return;
        }

        $oldStatus = $invoice->getOriginal('status');
        $newStatus = $invoice->status->value;
        $paymentMethod = $invoice->payment_method;

        // Solo actuar si el método de pago es crédito
        if ($paymentMethod !== PaymentMethodEnum::Credit->value) {
            return;
        }

        // 1. Factura emitida o pagada → crear cargo
        if (
            in_array($newStatus, [InvoiceStatusEnum::Issued->value, InvoiceStatusEnum::Paid->value])
            && $oldStatus === InvoiceStatusEnum::Draft->value
        ) {
            $this->creditService->charge(
                invoice: $invoice,
                amount: (float) $invoice->grand_total,
                dueDate: $invoice->due_at?->toDateString(),
            );
        }

        // 2. Factura cancelada → reversar cargo
        if (
            $newStatus === InvoiceStatusEnum::Cancelled->value
            && in_array($oldStatus, [InvoiceStatusEnum::Issued->value, InvoiceStatusEnum::Paid->value])
        ) {
            $this->creditService->reverseCharge($invoice);
        }
    }
}
```

### 6.2 Registro en FacturacionServiceProvider

```php
// Agregar en boot():
use App\Modules\Facturacion\Models\Invoice;
use App\Modules\Facturacion\Observers\InvoiceObserver;

public function boot(): void
{
    Invoice::observe(InvoiceObserver::class);
}
```

---

## 7. EXCEPCIÓN

### 7.1 CreditLimitExceededException

```php
<?php

declare(strict_types=1);

namespace App\Modules\Facturacion\Exceptions;

use App\Models\Contact;

class CreditLimitExceededException extends \RuntimeException
{
    public function __construct(
        public readonly Contact $contact,
        public readonly float $currentBalance,
        public readonly float $requestedAmount,
        public readonly float $creditLimit,
    ) {
        $newBalance = $this->currentBalance + $this->requestedAmount;

        parent::__construct(
            "El crédito de {$this->contact->name} excede el límite. "
            . "Balance actual: \$" . number_format($this->currentBalance, 2)
            . ", solicitado: \$" . number_format($this->requestedAmount, 2)
            . ", nuevo saldo: \$" . number_format($newBalance, 2)
            . ", límite: \$" . number_format($this->creditLimit, 2)
        );
    }
}
```

---

## 8. NOTIFICACIÓN

### 8.1 OverdueCreditNotification

```php
<?php

declare(strict_types=1);

namespace App\Modules\Facturacion\Notifications;

use App\Modules\Facturacion\Models\CreditAccount;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

class OverdueCreditNotification extends Notification implements ShouldQueue
{
    use Queueable;

    public function __construct(
        private CreditAccount $account,
        private float $overdueAmount,
        private int $daysOverdue,
    ) {}

    public function via(object $notifiable): array
    {
        return ['mail'];
    }

    public function toMail(object $notifiable): MailMessage
    {
        return (new MailMessage)
            ->subject("Cartera vencida: {$this->account->contact->name}")
            ->line("El cliente **{$this->account->contact->name}** tiene un saldo vencido.")
            ->line("Monto vencido: **\$" . number_format($this->overdueAmount, 2) . "**")
            ->line("Días de atraso: **{$this->daysOverdue}** días")
            ->line("Balance actual: **\$" . number_format((float) $this->account->current_balance, 2) . "**")
            ->action('Ver Cuenta', url("/admin/facturacion/credit-accounts/{$this->account->id}"))
            ->line('Por favor, gestione la cobranza lo antes posible.');
    }
}
```

---

## 9. COMANDO

### 9.1 CheckOverdueCreditsCommand

```php
<?php

declare(strict_types=1);

namespace App\Console\Commands\facturacion;

use App\Models\User;
use App\Modules\Facturacion\Notifications\OverdueCreditNotification;
use App\Modules\Facturacion\Services\CreditReportService;
use App\Services\TenantManager;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Notification;

class CheckOverdueCreditsCommand extends Command
{
    protected $signature = 'credit:check-overdue {--tenant= : Filter by specific tenant ID}';

    protected $description = 'Check for overdue credit accounts and send notifications';

    public function handle(CreditReportService $reportService): int
    {
        $tenantId = $this->option('tenant');

        if ($tenantId) {
            app(TenantManager::class)->setTenantContext($tenantId);
        }

        $overdueAccounts = $reportService->getOverdueAccounts();

        if ($overdueAccounts->isEmpty()) {
            $this->info('No overdue credit accounts found.');
            return self::SUCCESS;
        }

        $this->warn("Found {$overdueAccounts->count()} overdue accounts:");

        foreach ($overdueAccounts as $account) {
            $overdueAmount = (float) $account->overdueCharges()->sum('amount');
            $daysOverdue = (int) $account->overdueCharges()
                ->min('due_date')
                ?->diffInDays(now()) ?? 0;

            $this->line("  - {$account->contact->name}: \$" . number_format($overdueAmount, 2) . " ({$daysOverdue} days)");

            $this->notifyTenant($account, $overdueAmount, $daysOverdue);
        }

        return self::SUCCESS;
    }

    private function notifyTenant($account, float $overdueAmount, int $daysOverdue): void
    {
        $users = User::role(['owner', 'editor'])
            ->where('tenant_id', $account->tenant_id)
            ->get();

        if ($users->isEmpty()) {
            return;
        }

        Notification::send($users, new OverdueCreditNotification(
            account: $account,
            overdueAmount: $overdueAmount,
            daysOverdue: $daysOverdue,
        ));
    }
}
```

---

## 10. FILAMENT RESOURCES

### 10.1 CreditAccountResource

- **Form:** contact (select, searchable,preload), credit_limit (number), payment_terms_days (number), is_active (toggle), notes (textarea)
- **Table:** contact.name, credit_limit (money), current_balance (money, color red if > 0), available_credit (money), is_active (badge), overdue_amount (computed, color red)
- **Actions:** Registrar Pago (modal con amount, reference, notes), Ver Estado de Cuenta (page)
- **Filters:** is_active, has_overdue (custom)

### 10.2 CreditTransactionResource (Read-only)

- **Table:** credit_account.contact.name, type (badge), amount (money), due_date, paid_at, invoice.document_number, reference, created_at
- **Filters:** type, date range, has_invoice
- **No create/edit/delete actions**

### 10.3 Pages

- **CreditStatementPage:** Muestra estado de cuenta completo (statement) con Aging Report como widget

---

## 11. TESTS (18+)

### 11.1 CreditAccountTest (7 tests)

```
test_credit_account_can_be_created
test_credit_account_belongs_to_contact
test_credit_account_has_transactions
test_credit_account_is_overdue_when_balance_positive_and_overdue_charges_exist
test_credit_account_overdue_amount_calculates_correctly
test_credit_account_available_credit_is_generated
test_one_credit_account_per_contact_per_tenant
```

### 11.2 CreditTransactionTest (4 tests)

```
test_charge_increases_balance
test_payment_decreases_balance
test_charge_reverse_decreases_balance
test_transaction_belongs_to_invoice
```

### 11.3 CreditAccountServiceTest (5 tests)

```
test_charge_creates_transaction_and_updates_balance
test_charge_throws_on_inactive_account
test_charge_throws_on_limit_exceeded
test_payment_creates_transaction_and_updates_balance
test_reverse_charge_reverts_balance
```

### 11.4 InvoiceObserverCreditTest (3 tests)

```
test_credit_invoice_creates_charge_on_issue
test_credit_invoice_reverses_charge_on_cancel
test_non_credit_invoice_does_not_create_charge
```

### 11.5 CheckOverdueCreditsCommandTest (2 tests)

```
test_command_sends_notifications_for_overdue
test_command_no_output_when_no_overdue
```

---

## 12. ARCHIVOS A CREAR/MODIFICAR

### Nuevos (16 archivos)

| Archivo | Tipo |
|---------|------|
| `database/migrations/2026_08_28_000001_create_credit_accounts_table.php` | Migración |
| `database/migrations/2026_08_28_000002_create_credit_transactions_table.php` | Migración |
| `database/migrations/2026_08_28_000003_add_credit_account_id_to_invoices.php` | Migración |
| `app/Modules/Facturacion/Models/CreditAccount.php` | Modelo |
| `app/Modules/Facturacion/Models/CreditTransaction.php` | Modelo |
| `app/Modules/Facturacion/Services/CreditAccountService.php` | Servicio |
| `app/Modules/Facturacion/Services/CreditReportService.php` | Servicio |
| `app/Modules/Facturacion/Observers/InvoiceObserver.php` | Observer |
| `app/Modules/Facturacion/Exceptions/CreditLimitExceededException.php` | Excepción |
| `app/Modules/Facturacion/Notifications/OverdueCreditNotification.php` | Notificación |
| `app/Console/Commands/facturacion/CheckOverdueCreditsCommand.php` | Comando |
| `app/Filament/Resources/CreditAccountResource.php` | Resource |
| `app/Filament/Resources/CreditTransactionResource.php` | Resource |
| `database/factories/CreditAccountFactory.php` | Factory |
| `database/factories/CreditTransactionFactory.php` | Factory |
| `tests/Feature/Facturacion/CreditAccountTest.php` | Test |

### Modificar (3 archivos)

| Archivo | Cambio |
|---------|--------|
| `app/Modules/Facturacion/Providers/FacturacionServiceProvider.php` | Registrar InvoiceObserver |
| `app/Modules/Facturacion/Models/Invoice.php` | Agregar `credit_account_id` a $fillable + relación |
| `app/Enums/InvoiceStatusEnum.php` | Agregar caso `Confirmed` con label si falta |

---

## 13. ORDEN DE IMPLEMENTACIÓN

| Día | Tarea | Dependencias |
|-----|-------|--------------|
| 1 | Migrations (3) + Models (2) + Factories (2) + Enums | — |
| 1 | CreditAccountService + CreditLimitExceededException | Models |
| 2 | InvoiceObserver + OverdueCreditNotification + CheckOverdueCreditsCommand | Services |
| 2 | CreditReportService | Models |
| 3 | Filament Resources (2) + Pages | Services |
| 3 | Tests (18+) | Todo |
| 3 | Pint + suite completa verde | Todo |

---

## 14. EDGE CASES

| Caso | Comportamiento |
|------|----------------|
| Factura crédito se cancela | InvoiceObserver detecta → reverseCharge() → crea charge_reverse |
| Pago parcial | Cada pago es entrada separada en ledger. Aging busca charges sin pago. |
| Pago exceed deuda | Balance queda en 0 (no negativo). Crédito disponible crece. |
| Límite de crédito = 0 | Sin límite (crédito ilimitado). Solo valida si > 0. |
| Contacto sin cuenta | getOrCreateForContact() crea con credit_limit=0 |
| Tenant context missing en comando | BelongsToTenant lanza excepción (correcto) |
| Factura sin contact_id | Observer retorna sin action (factura POS sin cliente) |
| Múltiples facturas crédito | Cada una crea su propio charge. Aging las agrupa por cliente. |

---

## 15. NOTAS DE IMPLEMENTACIÓN

### 15.1 Sobre `paid_at` en charges
**DECISIÓN: NO usar `paid_at` en charges.** En su lugar:
- El aging busca charges que NO tengan un `payment` asociado por `invoice_id`
- Si hay `charge_reverse`, el cargo se considera cancelado
- Esto mantiene el ledger 100% inmutable (excepto el edge case de `paid_at` que no se usa)

### 15.2 Sobre `available_credit` GENERATED STORED
PostgreSQL calcula automáticamente. No se puede INSERT/UPDATE directamente. Si se necesita modificar, se debe hacer vía `ALTER TABLE`. Para el modelo Eloquent, se declara como `protected $computed = ['available_credit']` o se ignora en $fillable.

### 15.3 Sobre multi-tenancy del observer
El observer se ejecuta en el contexto de la request actual. El `tenant('id')` ya está establecido por el middleware. No necesita-setTenantContext() extra.

### 15.4 Sobre el aging report
El `CreditReportService::getAgingReport()` usa collection filtering (no SQL). Para volúmenes grandes (>10k transacciones), considerar migrar a SQL con window functions. Para el MVP actual es suficiente.

---

*Spec v2.0 — Phase 2 Cartera / Crédito (Fiado)*
*Adaptado al schema real de saas-core (2026-08-27)*
