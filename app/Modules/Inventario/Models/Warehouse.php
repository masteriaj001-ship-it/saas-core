<?php

declare(strict_types=1);

namespace App\Modules\Inventario\Models;

use App\Models\Location;
use App\Models\TenantModel;
use Database\Factories\WarehouseFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Warehouse extends TenantModel
{
    use HasFactory;

    protected static function newFactory(): WarehouseFactory
    {
        return WarehouseFactory::new();
    }

    protected $fillable = [
        'location_id',
        'code',
        'name',
        'address',
        'is_default',
        'is_active',
        'metadata',
    ];

    protected function casts(): array
    {
        return array_merge(parent::casts(), [
            'is_default' => 'boolean',
            'is_active' => 'boolean',
            'metadata' => 'array',
        ]);
    }

    public function location(): BelongsTo
    {
        return $this->belongsTo(Location::class);
    }

    public function stockMovements(): HasMany
    {
        return $this->hasMany(StockMovement::class);
    }
}
