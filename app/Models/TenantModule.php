<?php

declare(strict_types=1);

namespace App\Models;

class TenantModule extends TenantModel
{
    protected $fillable = [
        'module_slug',
        'is_active',
        'config',
        'activated_at',
    ];

    protected function casts(): array
    {
        return array_merge(parent::casts(), [
            'is_active'    => 'boolean',
            'config'       => 'array',
            'activated_at' => 'datetime',
        ]);
    }
}
