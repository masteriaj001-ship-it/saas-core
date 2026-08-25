<?php

declare(strict_types=1);

namespace App\Modules\Plataforma\Models;

use App\Models\Tenant;
use App\Models\User;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ImpersonationLog extends Model
{
    use HasUuids;

    protected $guarded = [
        'id',
        'created_at',
        'updated_at',
    ];

    protected $casts = [
        'id' => 'string',
        'superadmin_id' => 'string',
        'tenant_id' => 'string',
        'started_at' => 'datetime',
        'ended_at' => 'datetime',
    ];

    public function superadmin(): BelongsTo
    {
        return $this->belongsTo(User::class, 'superadmin_id');
    }

    public function tenant(): BelongsTo
    {
        return $this->belongsTo(Tenant::class);
    }

    public function isActive(): bool
    {
        return $this->ended_at === null;
    }
}
