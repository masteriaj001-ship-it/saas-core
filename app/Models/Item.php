<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;

class Item extends TenantModel
{
    use HasFactory;

    protected $fillable = [
        'sku',
        'name',
        'description',
        'item_type',
        'unit',
        'price',
        'cost',
        'stock',
        'min_stock',
        'metadata',
    ];

    protected function casts(): array
    {
        return array_merge(parent::casts(), [
            'price' => 'decimal:2',
            'cost' => 'decimal:2',
            'stock' => 'integer',
            'min_stock' => 'integer',
            'metadata' => 'array',
        ]);
    }
}
