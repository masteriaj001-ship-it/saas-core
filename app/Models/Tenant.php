<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Tenant extends Model
{
    use HasFactory, HasUuids;

    protected $fillable = [
        'name',
        'slug',
        'plan',
        'is_active',
        'settings',
        'onboarding_completed',
    ];

    protected $casts = [
        'id' => 'string',
        'is_active' => 'boolean',
        'settings' => 'array',
        'onboarding_completed' => 'boolean',
    ];
}
