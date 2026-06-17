<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;

class Tenant extends Model
{
    use HasFactory, HasUuids, SoftDeletes;

    protected $fillable = [
        'organization_id',
        'name',
        'slug',
        'plan',
        'is_active',
        'settings',
        'onboarding_completed',
    ];

    protected $casts = [
        'id' => 'string',
        'organization_id' => 'string',
        'is_active' => 'boolean',
        'onboarding_completed' => 'boolean',
        'settings' => 'array',
    ];

    public function organization(): BelongsTo
    {
        return $this->belongsTo(Organization::class);
    }

    public function esResponsableIva(): bool
    {
        return (bool) ($this->settings['es_responsable_iva'] ?? false);
    }
}
