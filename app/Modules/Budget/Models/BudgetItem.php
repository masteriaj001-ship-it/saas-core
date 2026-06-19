<?php

declare(strict_types=1);

namespace App\Modules\Budget\Models;

use App\Models\TenantModel;
use Database\Factories\BudgetItemFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class BudgetItem extends TenantModel
{
    use HasFactory;

    protected static function newFactory(): BudgetItemFactory
    {
        return BudgetItemFactory::new();
    }

    public $timestamps = true;

    protected $fillable = [
        'budget_id',
        'description',
        'quantity',
        'unit_price',
        'discount',
        'tax_rate',
        'subtotal',
        'total',
        'sort_order',
    ];

    protected $casts = [
        'quantity' => 'float',
        'unit_price' => 'float',
        'discount' => 'float',
        'tax_rate' => 'float',
        'subtotal' => 'float',
        'total' => 'float',
        'sort_order' => 'integer',
    ];

    public function budget(): BelongsTo
    {
        return $this->belongsTo(Budget::class);
    }
}
