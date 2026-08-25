<?php

declare(strict_types=1);

namespace App\Modules\Plataforma\Models;

use App\Models\Tenant;
use App\Models\User;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class SubscriptionLog extends Model
{
    use HasUuids;

    protected $guarded = [
        'id',
        'created_at',
        'updated_at',
    ];

    protected $casts = [
        'id' => 'string',
        'tenant_id' => 'string',
        'plan_from' => 'string',
        'plan_to' => 'string',
        'changed_by' => 'string',
        'changed_at' => 'datetime',
    ];

    public function tenant(): BelongsTo
    {
        return $this->belongsTo(Tenant::class);
    }

    public function planFrom(): BelongsTo
    {
        return $this->belongsTo(Plan::class, 'plan_from');
    }

    public function planTo(): BelongsTo
    {
        return $this->belongsTo(Plan::class, 'plan_to');
    }

    public function changedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'changed_by');
    }
}
