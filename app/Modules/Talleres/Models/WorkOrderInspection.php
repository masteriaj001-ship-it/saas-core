<?php

declare(strict_types=1);

namespace App\Modules\Talleres\Models;

use App\Enums\InspectionItemStatusEnum;
use App\Models\Concerns\BelongsToTenant;
use Database\Factories\WorkOrderInspectionFactory;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class WorkOrderInspection extends Model
{
    use BelongsToTenant, HasFactory, HasUuids;

    protected static function newFactory(): WorkOrderInspectionFactory
    {
        return WorkOrderInspectionFactory::new();
    }

    protected $fillable = [
        'work_order_id',
        'item_name',
        'status',
        'notes',
        'photo_path', // @deprecated — usar WorkOrderMedia en su lugar
        'sort_order',
    ];

    protected $guarded = [
        'id',
        'tenant_id',
        'created_at',
        'updated_at',
    ];

    public function workOrder(): BelongsTo
    {
        return $this->belongsTo(WorkOrder::class);
    }

    public function media(): HasMany
    {
        return $this->hasMany(WorkOrderMedia::class, 'work_order_inspection_id')
            ->orderBy('created_at', 'desc');
    }

    protected function casts(): array
    {
        return [
            'id' => 'string',
            'tenant_id' => 'string',
            'work_order_id' => 'string',
            'status' => InspectionItemStatusEnum::class,
            'sort_order' => 'integer',
            'created_at' => 'datetime',
            'updated_at' => 'datetime',
        ];
    }
}
