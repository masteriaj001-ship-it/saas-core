<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Transaction extends TenantModel
{
    use HasFactory;

    protected $fillable = [
        'contact_id',
        'type',
        'invoice_number',
        'cufe',
        'resolution_number',
        'status',
        'subtotal',
        'total_tax',
        'total_retentions',
        'total_amount',
        'payment_method',
        'notes',
        'created_by',
    ];

    public function contact(): BelongsTo
    {
        return $this->belongsTo(Contact::class);
    }

    public function items(): HasMany
    {
        return $this->hasMany(TransactionItem::class);
    }

    public function createdBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function isDraft(): bool
    {
        return $this->status === 'draft';
    }

    public function isIssued(): bool
    {
        return $this->status === 'issued';
    }

    public function isCancelled(): bool
    {
        return $this->status === 'cancelled';
    }

    public function canEdit(): bool
    {
        return $this->isDraft();
    }

    public function canIssue(): bool
    {
        return $this->isDraft();
    }

    public function canCancel(): bool
    {
        return $this->isIssued();
    }

    protected function casts(): array
    {
        return array_merge(parent::casts(), [
            'subtotal' => 'decimal:2',
            'total_tax' => 'decimal:2',
            'total_retentions' => 'decimal:2',
            'total_amount' => 'decimal:2',
        ]);
    }
}
