<?php

declare(strict_types=1);

namespace App\Modules\Talleres\Models;

use App\Enums\VehicleTypeEnum;
use App\Models\Contact;
use App\Models\TenantModel;
use Database\Factories\AssetFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Asset extends TenantModel
{
    use HasFactory;

    protected static function newFactory(): AssetFactory
    {
        return AssetFactory::new();
    }

    protected $fillable = [
        'name',
        'code',
        'plate',
        'vin',
        'brand',
        'model',
        'year',
        'vehicle_type',
        'asset_type',
        'status',
        'metadata',
        'acquired_at',
        'owner_id',
    ];

    protected function casts(): array
    {
        return array_merge(parent::casts(), [
            'metadata' => 'array',
            'acquired_at' => 'date',
            'disposed_at' => 'date',
            'vehicle_type' => VehicleTypeEnum::class,
        ]);
    }

    public function owner(): BelongsTo
    {
        return $this->belongsTo(Contact::class);
    }

    public function workOrders(): HasMany
    {
        return $this->hasMany(WorkOrder::class);
    }
}
