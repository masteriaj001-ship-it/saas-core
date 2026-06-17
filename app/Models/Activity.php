<?php

declare(strict_types=1);

namespace App\Models;

use App\Models\Concerns\BelongsToTenant;
use Spatie\Activitylog\Models\Activity as SpatieActivity;

class Activity extends SpatieActivity
{
    use BelongsToTenant;

    public bool $ignoresMissingTenantContext = true;

    public $guarded = [
        'id',
        'tenant_id',
        'created_at',
        'updated_at',
    ];
}
