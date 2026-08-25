<?php

declare(strict_types=1);

namespace App\Modules\Talleres\Models;

use App\Enums\WorkOrderChecklistStatusEnum;
use App\Enums\WorkOrderMediaStageEnum;
use App\Enums\WorkOrderStatusEnum;
use App\Models\Concerns\Auditable;
use App\Models\Contact;
use App\Models\Location;
use App\Models\TenantModel;
use App\Modules\Facturacion\Models\Invoice;
use Database\Factories\WorkOrderFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class WorkOrder extends TenantModel
{
    use Auditable, HasFactory;

    protected static function newFactory(): WorkOrderFactory
    {
        return WorkOrderFactory::new();
    }

    protected $fillable = [
        'asset_id',
        'client_vehicle_id',
        'contact_id',
        'location_id',
        'code',
        'title',
        'internal_notes',
        'client_report',
        'priority',
        'status',
        'started_at',
        'completed_at',
        'metadata',
        'mechanic_id',
        'advisor_id',
        'mileage_km',
        'battery_level',
        'aesthetic_notes',
        'settings',
        'signature_hash',
        'signed_at',
        'closure_notes',
        'approval_at',
        'approval_channel',
    ];

    protected function casts(): array
    {
        return array_merge(parent::casts(), [
            'metadata' => 'array',
            'settings' => 'array',
            'started_at' => 'datetime',
            'completed_at' => 'datetime',
            'approval_at' => 'datetime',
            'delivery_at' => 'datetime',
            'signed_at' => 'datetime',
            'qc_passed' => 'boolean',
            'mileage_km' => 'integer',
            'status' => WorkOrderStatusEnum::class,
            'client_report' => 'string',
            'internal_notes' => 'string',
            'estimated_completion_at' => 'datetime',
            'actual_started_at' => 'datetime',
            'actual_completed_at' => 'datetime',
        ]);
    }

    public function isLegacyClosure(): bool
    {
        return (bool) ($this->settings['is_legacy_closure'] ?? false);
    }

    public function hasCompleteFinalChecklist(): bool
    {
        return $this->checklistItems()
            ->where('status', WorkOrderChecklistStatusEnum::Pending)
            ->doesntExist();
    }

    public function hasBeforeAfterPhotos(): bool
    {
        return $this->media()->where('stage', WorkOrderMediaStageEnum::Before)->exists()
            && $this->media()->where('stage', WorkOrderMediaStageEnum::After)->exists();
    }

    public function asset(): BelongsTo
    {
        return $this->belongsTo(Asset::class);
    }

    public function clientVehicle(): BelongsTo
    {
        return $this->belongsTo(ClientVehicle::class, 'client_vehicle_id');
    }

    public function contact(): BelongsTo
    {
        return $this->belongsTo(Contact::class);
    }

    public function location(): BelongsTo
    {
        return $this->belongsTo(Location::class);
    }

    public function mechanic(): BelongsTo
    {
        return $this->belongsTo(Contact::class, 'mechanic_id');
    }

    public function advisor(): BelongsTo
    {
        return $this->belongsTo(Contact::class, 'advisor_id');
    }

    public function items(): HasMany
    {
        return $this->hasMany(WorkOrderItem::class);
    }

    public function activities(): HasMany
    {
        return $this->hasMany(WorkOrderActivity::class)->orderBy('created_at');
    }

    public function inspections(): HasMany
    {
        return $this->hasMany(WorkOrderInspection::class)->orderBy('sort_order');
    }

    public function checklistItems(): HasMany
    {
        return $this->hasMany(WorkOrderChecklistItem::class)->orderBy('position');
    }

    public function media(): HasMany
    {
        return $this->hasMany(WorkOrderMedia::class)->orderBy('created_at', 'desc');
    }

    public function generalMedia(): HasMany
    {
        return $this->media()->whereNull('work_order_inspection_id');
    }

    public function invoices(): HasMany
    {
        return $this->hasMany(Invoice::class);
    }
}
