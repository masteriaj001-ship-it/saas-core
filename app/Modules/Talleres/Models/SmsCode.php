<?php

declare(strict_types=1);

namespace App\Modules\Talleres\Models;

use App\Models\Concerns\BelongsToTenant;
use App\Models\Tenant;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class SmsCode extends Model
{
    use BelongsToTenant, HasUuids;

    protected $guarded = [
        'id',
        'tenant_id',
        'created_at',
        'updated_at',
    ];

    protected $fillable = [
        'tenant_id',
        'work_order_id',
        'code',
        'expires_at',
        'send_count',
        'attempts',
        'used_at',
    ];

    protected function casts(): array
    {
        return [
            'id' => 'string',
            'tenant_id' => 'string',
            'expires_at' => 'datetime',
            'used_at' => 'datetime',
            'send_count' => 'integer',
            'attempts' => 'integer',
            'created_at' => 'datetime',
            'updated_at' => 'datetime',
        ];
    }

    public function workOrder(): BelongsTo
    {
        return $this->belongsTo(WorkOrder::class);
    }

    public function tenant(): BelongsTo
    {
        return $this->belongsTo(Tenant::class);
    }

    public function isValid(): bool
    {
        return $this->used_at === null
            && $this->expires_at->isFuture()
            && $this->attempts < 5;
    }

    public function canResend(): bool
    {
        return $this->send_count < 3;
    }
}
