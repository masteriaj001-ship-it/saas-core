<?php

declare(strict_types=1);

namespace App\Modules\Talleres\Models;

use App\Enums\FuelTypeEnum;
use App\Enums\VehicleTypeEnum;
use App\Models\Concerns\Auditable;
use App\Models\Contact;
use App\Models\TenantModel;
use Database\Factories\AssetFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
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
        'plate',
        'vin',
        'brand',
        'model',
        'year',
        'version',
        'engine_number',
        'current_mileage',
        'fuel_type',
        'color',
        'vehicle_type',
        'asset_type',
        'status',
        'metadata',
        'acquired_at',
        'owner_contact_id',
    ];

    protected function casts(): array
    {
        return array_merge(parent::casts(), [
            'metadata' => 'array',
            'acquired_at' => 'date',
            'disposed_at' => 'date',
            'year' => 'integer',
            'current_mileage' => 'integer',
            'vehicle_type' => VehicleTypeEnum::class,
            'fuel_type' => FuelTypeEnum::class,
        ]);
    }

    public function owner(): BelongsTo
    {
        return $this->belongsTo(Contact::class, 'owner_contact_id');
    }

    public function workOrders(): HasMany
    {
        return $this->hasMany(WorkOrder::class);
    }

    public function isVehicle(): bool
    {
        return $this->asset_type === 'vehicle';
    }
}
