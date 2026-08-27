<?php

declare(strict_types=1);

namespace App\Modules\Inventario\Models;

use App\Models\TenantModel;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ItemCostHistory extends TenantModel
{
    use HasFactory;

    protected $table = 'item_cost_histories';

    protected $fillable = [
        'item_id',
        'previous_cost',
        'new_cost',
        'quantity_affected',
        'stock_before',
        'stock_after',
        'source_type',
        'source_id',
    ];

    protected function casts(): array
    {
        return array_merge(parent::casts(), [
            'previous_cost' => 'decimal:2',
            'new_cost' => 'decimal:2',
            'quantity_affected' => 'decimal:4',
            'stock_before' => 'decimal:4',
            'stock_after' => 'decimal:4',
        ]);
    }

    public function item(): BelongsTo
    {
        return $this->belongsTo(Item::class);
    }
}
