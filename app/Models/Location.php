<?php

declare(strict_types=1);

namespace App\Models;

use App\Modules\Talleres\Models\WorkOrder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\HasMany;

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

    public function workOrders(): HasMany
    {
        return $this->hasMany(WorkOrder::class);
    }
}
