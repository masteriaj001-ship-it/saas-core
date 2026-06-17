<?php

declare(strict_types=1);

namespace App\Modules\Talleres\Models;

use App\Enums\WorkOrderChecklistStatusEnum;
use App\Models\Concerns\BelongsToTenant;
use App\Models\Contact;
use Database\Factories\WorkOrderChecklistItemFactory;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;

class WorkOrderChecklistItem extends Model
{
    use BelongsToTenant, HasFactory, HasUuids, SoftDeletes;

    protected static function newFactory(): WorkOrderChecklistItemFactory
    {
        return WorkOrderChecklistItemFactory::new();
    }

    protected $fillable = [
        'work_order_id',
        'task',
        'status',
        'position',
        'notes',
        'assigned_to',
        'completed_by',
        'completed_at',
    ];

    protected $guarded = [
        'id',
        'tenant_id',
        'created_at',
        'updated_at',
        'deleted_at',
    ];

    public function workOrder(): BelongsTo
    {
        return $this->belongsTo(WorkOrder::class);
    }

    public function assignee(): BelongsTo
    {
        return $this->belongsTo(Contact::class, 'assigned_to');
    }

    public function completer(): BelongsTo
    {
        return $this->belongsTo(Contact::class, 'completed_by');
    }

    protected function casts(): array
    {
        return [
            'id' => 'string',
            'tenant_id' => 'string',
            'work_order_id' => 'string',
            'status' => WorkOrderChecklistStatusEnum::class,
            'position' => 'integer',
            'completed_at' => 'datetime',
            'assigned_to' => 'string',
            'completed_by' => 'string',
            'created_at' => 'datetime',
            'updated_at' => 'datetime',
            'deleted_at' => 'datetime',
        ];
    }
}
