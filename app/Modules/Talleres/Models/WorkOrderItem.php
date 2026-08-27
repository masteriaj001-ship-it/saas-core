<?php

declare(strict_types=1);

namespace App\Modules\Talleres\Models;

use App\Enums\WorkOrderItemTypeEnum;
use App\Models\Item;
use App\Models\TenantModel;
use Database\Factories\WorkOrderItemFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class WorkOrderItem extends TenantModel
{
    use HasFactory;

    protected static function newFactory(): WorkOrderItemFactory
    {
        return WorkOrderItemFactory::new();
    }

    protected $fillable = [
        'work_order_id',
        'item_id',
        'service_catalog_id',
        'quantity',
        'unit_price',
        'description',
        'metadata',
        'type',
        'stock_movement_id',
        'unit_cost_at_sale',
    ];

    protected function casts(): array
    {
        return array_merge(parent::casts(), [
            'quantity' => 'decimal:4',
            'unit_price' => 'decimal:4',
            'unit_cost_at_sale' => 'decimal:2',
            'metadata' => 'array',
            'type' => WorkOrderItemTypeEnum::class,
        ]);
    }

    public function workOrder(): BelongsTo
    {
        return $this->belongsTo(WorkOrder::class);
    }

    public function item(): BelongsTo
    {
        return $this->belongsTo(Item::class);
    }

    public function serviceCatalog(): BelongsTo
    {
        return $this->belongsTo(ServiceCatalog::class, 'service_catalog_id');
    }
}
