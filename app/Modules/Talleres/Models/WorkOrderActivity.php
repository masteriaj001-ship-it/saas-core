<?php

declare(strict_types=1);

namespace App\Modules\Talleres\Models;

use App\Enums\WorkOrderActivityTypeEnum;
use App\Models\Concerns\BelongsToTenant;
use App\Models\User;
use Database\Factories\WorkOrderActivityFactory;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class WorkOrderActivity extends Model
{
    use BelongsToTenant, HasFactory, HasUuids;

    protected static function newFactory(): WorkOrderActivityFactory
    {
        return WorkOrderActivityFactory::new();
    }

    protected $fillable = [
        'work_order_id',
        'user_id',
        'type',
        'description',
        'from_status',
        'to_status',
        'metadata',
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

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    protected function casts(): array
    {
        return [
            'id' => 'string',
            'tenant_id' => 'string',
            'work_order_id' => 'string',
            'user_id' => 'string',
            'type' => WorkOrderActivityTypeEnum::class,
            'metadata' => 'array',
            'created_at' => 'datetime',
            'updated_at' => 'datetime',
        ];
    }
}
