<?php

declare(strict_types=1);

namespace App\Modules\Inventario\Models;

use App\Models\TenantModel;
use Database\Factories\PriceListItemFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class PriceListItem extends TenantModel
{
    use HasFactory;

    protected static function newFactory(): PriceListItemFactory
    {
        return PriceListItemFactory::new();
    }

    protected $fillable = [
        'price_list_id',
        'item_id',
        'price',
        'min_quantity',
        'metadata',
    ];

    protected function casts(): array
    {
        return array_merge(parent::casts(), [
            'price' => 'decimal:2',
            'min_quantity' => 'integer',
            'metadata' => 'array',
        ]);
    }

    public function priceList(): BelongsTo
    {
        return $this->belongsTo(PriceList::class);
    }

    public function item(): BelongsTo
    {
        return $this->belongsTo(Item::class);
    }
}
