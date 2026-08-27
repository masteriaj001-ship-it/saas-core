<?php

declare(strict_types=1);

namespace App\Modules\Facturacion\Models;

use App\Models\Concerns\BelongsToTenant;
use App\Models\TenantModel;
use Database\Factories\CreditTransactionFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class CreditTransaction extends TenantModel
{
    use BelongsToTenant, HasFactory;

    protected static function newFactory(): CreditTransactionFactory
    {
        return CreditTransactionFactory::new();
    }

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

    public function creditAccount(): BelongsTo
    {
        return $this->belongsTo(CreditAccount::class);
    }

    public function invoice(): BelongsTo
    {
        return $this->belongsTo(Invoice::class);
    }

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
