<?php

declare(strict_types=1);

namespace App\Modules\Talleres\Models;

use App\Models\TenantModel;
use Database\Factories\AssetFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;

class Asset extends TenantModel
{
    use HasFactory;

    protected static function newFactory(): AssetFactory
    {
        return AssetFactory::new();
    }

    protected $fillable = [
        'name',
        'code',
        'plate',
        'brand',
        'model',
        'year',
        'asset_type',
        'status',
        'metadata',
        'acquired_at',
    ];

    protected function casts(): array
    {
        return array_merge(parent::casts(), [
            'metadata' => 'array',
            'acquired_at' => 'date',
            'disposed_at' => 'date',
        ]);
    }
}
