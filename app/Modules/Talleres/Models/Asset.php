<?php

declare(strict_types=1);

namespace App\Modules\Talleres\Models;

use App\Models\Concerns\Auditable;
use App\Models\TenantModel;
use Database\Factories\AssetFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Asset extends TenantModel
{
    use Auditable, HasFactory;

    protected static function newFactory(): AssetFactory
    {
        return AssetFactory::new();
    }

    protected $fillable = [
        'name',
        'code',
        'asset_type',
        'status',
        'metadata',
        'acquired_at',
    ];

    protected function casts(): array
    {
        return array_merge(parent::casts(), [
            'metadata' => 'array',
            'acquired_at' => 'date',
            'disposed_at' => 'date',
        ]);
    }

    public function workOrders(): HasMany
    {
        return $this->hasMany(WorkOrder::class);
    }
}
