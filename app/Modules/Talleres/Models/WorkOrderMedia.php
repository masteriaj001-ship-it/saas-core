<?php

declare(strict_types=1);

namespace App\Modules\Talleres\Models;

use App\Models\Concerns\BelongsToTenant;
use App\Models\User;
use Database\Factories\WorkOrderMediaFactory;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class WorkOrderMedia extends Model
{
    use BelongsToTenant, HasFactory, HasUuids;

    protected static function newFactory(): WorkOrderMediaFactory
    {
        return WorkOrderMediaFactory::new();
    }

    protected $fillable = [
        'work_order_id',
        'work_order_inspection_id',
        'user_id',
        'original_name',
        'storage_path',
        'mime_type',
        'size',
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

    public function inspection(): BelongsTo
    {
        return $this->belongsTo(WorkOrderInspection::class, 'work_order_inspection_id');
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
            'work_order_inspection_id' => 'string',
            'user_id' => 'string',
            'size' => 'integer',
            'metadata' => 'array',
            'created_at' => 'datetime',
            'updated_at' => 'datetime',
        ];
    }
}
