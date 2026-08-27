<?php

declare(strict_types=1);

namespace App\Modules\Inventario\Models;

use App\Models\Item;
use App\Models\TenantModel;
use Database\Factories\PurchaseOrderItemFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class PurchaseOrderItem extends TenantModel
{
    use HasFactory;

    protected static function newFactory(): PurchaseOrderItemFactory
    {
        return PurchaseOrderItemFactory::new();
    }

    protected $fillable = [
        'purchase_order_id',
        'item_id',
        'description',
        'quantity',
        'received_quantity',
        'unit_cost',
        'tax_rate',
        'tax_amount',
        'subtotal',
        'batch_number',
        'expires_at',
        'metadata',
    ];

    protected function casts(): array
    {
        return array_merge(parent::casts(), [
            'quantity' => 'decimal:4',
            'received_quantity' => 'decimal:4',
            'unit_cost' => 'decimal:2',
            'tax_rate' => 'decimal:2',
            'tax_amount' => 'decimal:2',
            'subtotal' => 'decimal:2',
            'expires_at' => 'date',
            'metadata' => 'array',
        ]);
    }

    public function purchaseOrder(): BelongsTo
    {
        return $this->belongsTo(PurchaseOrder::class);
    }

    public function item(): BelongsTo
    {
        return $this->belongsTo(Item::class);
    }

    public function pendingQuantity(): float
    {
        return (float) $this->quantity - (float) $this->received_quantity;
    }

    public function isFullyReceived(): bool
    {
        return $this->pendingQuantity() <= 0;
    }
}
