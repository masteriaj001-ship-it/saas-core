<?php

declare(strict_types=1);

namespace App\Modules\Talleres\Models;

use App\Models\Location;
use App\Models\TenantModel;
use App\Models\User;
use Database\Factories\WorkshopBayFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class WorkshopBay extends TenantModel
{
    use HasFactory;

    protected static function newFactory(): WorkshopBayFactory
    {
        return WorkshopBayFactory::new();
    }

    protected $table = 'workshop_bays';

    protected $fillable = [
        'location_id',
        'code',
        'name',
        'type',
        'is_active',
        'metadata',
        'created_by',
        'updated_by',
    ];

    protected function casts(): array
    {
        return array_merge(parent::casts(), [
            'metadata' => 'array',
            'is_active' => 'boolean',
        ]);
    }

    public function location(): BelongsTo
    {
        return $this->belongsTo(Location::class);
    }

    public function workOrders(): HasMany
    {
        return $this->hasMany(WorkOrder::class, 'bay_id');
    }

    public function appointments(): HasMany
    {
        return $this->hasMany(Appointment::class, 'bay_id');
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
