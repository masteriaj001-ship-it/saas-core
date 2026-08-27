<?php

declare(strict_types=1);

namespace App\Models;

use App\Models\Concerns\Auditable;
use App\Modules\Inventario\Models\StockMovement;
use App\Modules\Inventario\Models\Warehouse;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Item extends TenantModel
{
    use Auditable, HasFactory;

    protected $fillable = [
        'sku',
        'name',
        'description',
        'item_type',
        'unit',
        'price',
        'cost',
        'average_cost',
        'stock',
        'min_stock',
        'default_warehouse_id',
        'metadata',
        'created_by',
        'updated_by',
    ];

    protected function casts(): array
    {
        return array_merge(parent::casts(), [
            'price' => 'decimal:2',
            'cost' => 'decimal:2',
            'average_cost' => 'decimal:2',
            'stock' => 'integer',
            'min_stock' => 'integer',
            'metadata' => 'array',
        ]);
    }

    public function defaultWarehouse(): BelongsTo
    {
        return $this->belongsTo(Warehouse::class, 'default_warehouse_id');
    }

    public function stockMovements(): HasMany
    {
        return $this->hasMany(StockMovement::class, 'item_id');
    }

    public function isLowStock(): bool
    {
        return $this->stock < $this->min_stock;
    }

    public function hasStock(int $quantity): bool
    {
        return $this->stock >= $quantity;
    }
}
