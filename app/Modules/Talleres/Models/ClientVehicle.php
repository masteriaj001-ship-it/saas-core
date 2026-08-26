<?php

declare(strict_types=1);

namespace App\Modules\Talleres\Models;

use App\Enums\FuelTypeEnum;
use App\Enums\VehicleTypeEnum;
use App\Models\Contact;
use App\Models\TenantModel;
use Database\Factories\ClientVehicleFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class ClientVehicle extends TenantModel
{
    use HasFactory;

    protected static function newFactory(): ClientVehicleFactory
    {
        return ClientVehicleFactory::new();
    }

    protected $fillable = [
        'owner_contact_id',
        'plate',
        'brand',
        'model',
        'version',
        'year',
        'vin',
        'engine_number',
        'color',
        'fuel_type',
        'vehicle_type',
        'current_mileage',
        'notes',
        'metadata',
    ];

    protected function casts(): array
    {
        return array_merge(parent::casts(), [
            'metadata' => 'array',
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
        return $this->hasMany(WorkOrder::class, 'client_vehicle_id');
    }

    public function mileageLogs(): HasMany
    {
        return $this->hasMany(VehicleMileageLog::class, 'client_vehicle_id');
    }

    public function recordMileage(int $mileage, ?string $workOrderId = null, ?string $notes = null): VehicleMileageLog
    {
        return $this->mileageLogs()->create([
            'tenant_id' => $this->tenant_id,
            'work_order_id' => $workOrderId,
            'mileage' => $mileage,
            'recorded_at' => now(),
            'notes' => $notes,
        ]);
    }

    public function scopeByPlate($query, string $plate)
    {
        return $query->where('plate', $plate);
    }

    public function scopeByOwner($query, string $ownerContactId)
    {
        return $query->where('owner_contact_id', $ownerContactId);
    }
}
