<?php

declare(strict_types=1);

namespace App\Modules\Inventario\Models;

use App\Models\Contact;
use App\Models\TenantModel;
use App\Models\User;
use Database\Factories\SupplierFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Supplier extends TenantModel
{
    use HasFactory;

    protected static function newFactory(): SupplierFactory
    {
        return SupplierFactory::new();
    }

    protected $fillable = [
        'contact_id',
        'code',
        'trade_name',
        'payment_terms_days',
        'credit_limit',
        'lead_time_days',
        'notes',
        'is_active',
        'metadata',
        'created_by',
        'updated_by',
    ];

    protected function casts(): array
    {
        return array_merge(parent::casts(), [
            'metadata' => 'array',
            'is_active' => 'boolean',
            'credit_limit' => 'decimal:2',
            'payment_terms_days' => 'integer',
            'lead_time_days' => 'integer',
        ]);
    }

    public function contact(): BelongsTo
    {
        return $this->belongsTo(Contact::class);
    }

    public function purchaseOrders(): HasMany
    {
        return $this->hasMany(PurchaseOrder::class);
    }

    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function updater(): BelongsTo
    {
        return $this->belongsTo(User::class, 'updated_by');
    }

    public function getDisplayNameAttribute(): string
    {
        return $this->trade_name ?? $this->contact->name;
    }
}
