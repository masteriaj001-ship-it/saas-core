<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class WorkOrder extends TenantModel
{
    use HasFactory;

    protected $fillable = [
        'asset_id',
        'contact_id',
        'code',
        'title',
        'description',
        'service_description',
        'priority',
        'status',
        'started_at',
        'completed_at',
        'metadata',
    ];

    protected function casts(): array
    {
        return array_merge(parent::casts(), [
            'metadata' => 'array',
            'started_at' => 'datetime',
            'completed_at' => 'datetime',
        ]);
    }

    public function asset(): BelongsTo
    {
        return $this->belongsTo(Asset::class);
    }

    public function contact(): BelongsTo
    {
        return $this->belongsTo(Contact::class);
    }

    public function items(): HasMany
    {
        return $this->hasMany(WorkOrderItem::class);
    }
}
