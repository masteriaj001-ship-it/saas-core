<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;

class Asset extends TenantModel
{
    use HasFactory;

    protected $fillable = [
        'name',
        'code',
        'asset_type',
        'status',
        'metadata',
        'acquired_at',
    ];

    protected function casts(): array
    {
        return array_merge(parent::casts(), [
            'metadata'    => 'array',
            'acquired_at' => 'date',
            'disposed_at' => 'date',
        ]);
    }
}
