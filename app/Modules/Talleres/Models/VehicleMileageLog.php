<?php

declare(strict_types=1);

namespace App\Modules\Talleres\Models;

use App\Models\TenantModel;
use Database\Factories\VehicleMileageLogFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class VehicleMileageLog extends TenantModel
{
    use HasFactory;

    protected static function newFactory(): VehicleMileageLogFactory
    {
        return VehicleMileageLogFactory::new();
    }

    protected $fillable = [
        'client_vehicle_id',
        'work_order_id',
        'mileage',
        'recorded_at',
        'notes',
    ];

    protected function casts(): array
    {
        return array_merge(parent::casts(), [
            'mileage' => 'integer',
            'recorded_at' => 'datetime',
        ]);
    }

    public function clientVehicle(): BelongsTo
    {
        return $this->belongsTo(ClientVehicle::class, 'client_vehicle_id');
    }

    public function workOrder(): BelongsTo
    {
        return $this->belongsTo(WorkOrder::class, 'work_order_id');
    }
}
