<?php

declare(strict_types=1);

namespace App\Modules\Talleres\Models;

use App\Models\Contact;
use App\Models\TenantModel;
use App\Models\User;
use App\Modules\Talleres\Enums\AppointmentStatus;
use Database\Factories\AppointmentFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Appointment extends TenantModel
{
    use HasFactory;

    protected static function newFactory(): AppointmentFactory
    {
        return AppointmentFactory::new();
    }

    protected $fillable = [
        'contact_id',
        'client_vehicle_id',
        'location_id',
        'bay_id',
        'mechanic_id',
        'title',
        'description',
        'status',
        'scheduled_at',
        'duration_minutes',
        'started_at',
        'completed_at',
        'notes',
        'metadata',
        'created_by',
        'updated_by',
    ];

    protected function casts(): array
    {
        return array_merge(parent::casts(), [
            'status' => AppointmentStatus::class,
            'metadata' => 'array',
            'scheduled_at' => 'datetime',
            'started_at' => 'datetime',
            'completed_at' => 'datetime',
            'duration_minutes' => 'integer',
        ]);
    }

    public function contact(): BelongsTo
    {
        return $this->belongsTo(Contact::class);
    }

    public function vehicle(): BelongsTo
    {
        return $this->belongsTo(ClientVehicle::class, 'client_vehicle_id');
    }

    public function location(): BelongsTo
    {
        return $this->belongsTo(Location::class);
    }

    public function bay(): BelongsTo
    {
        return $this->belongsTo(WorkshopBay::class);
    }

    public function mechanic(): BelongsTo
    {
        return $this->belongsTo(User::class, 'mechanic_id');
    }

    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function updater(): BelongsTo
    {
        return $this->belongsTo(User::class, 'updated_by');
    }

    public function scheduledEnd(): \DateTime
    {
        return $this->scheduled_at->clone()->addMinutes($this->duration_minutes);
    }

    public function overlapsWith(\DateTime $start, \DateTime $end, ?string $excludeId = null): bool
    {
        $query = self::where('bay_id', $this->bay_id)
            ->whereIn('status', ['scheduled', 'confirmed', 'in_progress'])
            ->where(function ($q) use ($start, $end) {
                $q->whereBetween('scheduled_at', [$start, $end])
                    ->orWhereRaw('scheduled_at + (duration_minutes || \' minutes\')::interval BETWEEN ? AND ?', [$start, $end]);
            });

        if ($excludeId) {
            $query->where('id', '!=', $excludeId);
        }

        return $query->exists();
    }
}
