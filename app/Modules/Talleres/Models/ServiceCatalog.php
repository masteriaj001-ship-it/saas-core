<?php

declare(strict_types=1);

namespace App\Modules\Talleres\Models;

use App\Models\TenantModel;
use Illuminate\Database\Eloquent\Factories\HasFactory;

class ServiceCatalog extends TenantModel
{
    use HasFactory;

    protected $fillable = [
        'name',
        'description',
        'base_price',
        'estimated_minutes',
        'is_active',
    ];

    protected function casts(): array
    {
        return array_merge(parent::casts(), [
            'base_price' => 'decimal:2',
            'estimated_minutes' => 'integer',
            'is_active' => 'boolean',
        ]);
    }
}
