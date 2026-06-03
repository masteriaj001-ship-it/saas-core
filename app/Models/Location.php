<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;

class Location extends TenantModel
{
    use HasFactory;

    protected $fillable = [
        'name',
        'address',
        'is_main',
        'is_active',
    ];

    protected function casts(): array
    {
        return array_merge(parent::casts(), [
            'is_main' => 'boolean',
            'is_active' => 'boolean',
        ]);
    }
}
