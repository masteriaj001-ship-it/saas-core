<?php

declare(strict_types=1);

namespace App\Modules\Inventario\Models;

use App\Models\TenantModel;
use App\Models\User;
use Database\Factories\PriceListFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\HasMany;

class PriceList extends TenantModel
{
    use HasFactory;

    protected static function newFactory(): PriceListFactory
    {
        return PriceListFactory::new();
    }

    protected $fillable = [
        'name',
        'description',
        'is_default',
        'is_active',
        'metadata',
        'created_by',
        'updated_by',
    ];

    protected function casts(): array
    {
        return array_merge(parent::casts(), [
            'metadata' => 'array',
            'is_default' => 'boolean',
            'is_active' => 'boolean',
        ]);
    }

    protected static function booted(): void
    {
        static::saving(function ($priceList) {
            if ($priceList->is_default) {
                self::where('id', '!=', $priceList->id)->update(['is_default' => false]);
            }
        });
    }

    public function items(): HasMany
    {
        return $this->hasMany(PriceListItem::class);
    }

    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function updater(): BelongsTo
    {
        return $this->belongsTo(User::class, 'updated_by');
    }
}
