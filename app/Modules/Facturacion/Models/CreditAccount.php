<?php

declare(strict_types=1);

namespace App\Modules\Facturacion\Models;

use App\Models\Concerns\BelongsToTenant;
use App\Models\Contact;
use App\Models\TenantModel;
use Database\Factories\CreditAccountFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class CreditAccount extends TenantModel
{
    use BelongsToTenant, HasFactory;

    protected static function newFactory(): CreditAccountFactory
    {
        return CreditAccountFactory::new();
    }

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
