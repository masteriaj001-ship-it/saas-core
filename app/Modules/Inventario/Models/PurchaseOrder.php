<?php

declare(strict_types=1);

namespace App\Modules\Inventario\Models;

use App\Models\TenantModel;
use App\Models\User;
use App\Modules\Inventario\Enums\PurchaseStatus;
use Database\Factories\PurchaseOrderFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class PurchaseOrder extends TenantModel
{
    use HasFactory;

    protected static function newFactory(): PurchaseOrderFactory
    {
        return PurchaseOrderFactory::new();
    }

    protected $fillable = [
        'supplier_id',
        'warehouse_id',
        'code',
        'status',
        'ordered_at',
        'expected_at',
        'received_at',
        'subtotal',
        'tax_total',
        'total',
        'notes',
        'metadata',
        'created_by',
        'updated_by',
    ];

    protected function casts(): array
    {
        return array_merge(parent::casts(), [
            'status' => PurchaseStatus::class,
            'metadata' => 'array',
            'ordered_at' => 'datetime',
            'expected_at' => 'datetime',
            'received_at' => 'datetime',
            'subtotal' => 'decimal:2',
            'tax_total' => 'decimal:2',
            'total' => 'decimal:2',
        ]);
    }

    public function supplier(): BelongsTo
    {
        return $this->belongsTo(Supplier::class);
    }

    public function warehouse(): BelongsTo
    {
        return $this->belongsTo(Warehouse::class);
    }

    public function items(): HasMany
    {
        return $this->hasMany(PurchaseOrderItem::class);
    }

    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function updater(): BelongsTo
    {
        return $this->belongsTo(User::class, 'updated_by');
    }

    public function canBeCancelled(): bool
    {
        return in_array($this->status, [
            PurchaseStatus::DRAFT,
            PurchaseStatus::ORDERED,
        ]) && $this->items()->sum('received_quantity') === 0.0;
    }

    public function isFullyReceived(): bool
    {
        return $this->items()
            ->whereColumn('received_quantity', '<', 'quantity')
            ->doesntExist();
    }
}
