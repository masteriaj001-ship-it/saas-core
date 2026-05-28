<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class WorkOrderItem extends TenantModel
{
    use HasFactory;

    protected $fillable = [
        'work_order_id',
        'item_id',
        'quantity',
        'unit_price',
        'description',
        'metadata',
    ];

    protected function casts(): array
    {
        return array_merge(parent::casts(), [
            'quantity'   => 'decimal:4',
            'unit_price' => 'decimal:4',
            'metadata'   => 'array',
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
}
