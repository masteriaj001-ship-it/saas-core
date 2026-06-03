<?php

declare(strict_types=1);

namespace App\Models\Concerns;

use App\Models\Tenant;
use App\Services\TenantManager;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use RuntimeException;

trait BelongsToTenant
{
    public static function bootBelongsToTenant(): void
    {
        static::addGlobalScope('tenant', function (Builder $builder) {
            $tenantManager = app(TenantManager::class);

            if (! $tenantManager->hasContext()) {
                return;
            }

            $builder->where(
                $builder->getModel()->getTable().'.tenant_id',
                $tenantManager->getCurrentTenantId()
            );
        });

        static::creating(function (Model $model) {
            if (empty($model->tenant_id)) {
                $isSuperadmin = isset($model->is_superadmin) && $model->is_superadmin;

                if ($isSuperadmin) {
                    return;
                }

                $tenantManager = app(TenantManager::class);

                if (! $tenantManager->hasContext()) {
                    throw new RuntimeException(
                        'Cannot create '.static::class.' without tenant context.'
                    );
                }

                $model->tenant_id = $tenantManager->getCurrentTenantId();
            }
        });
    }

    public function tenant(): BelongsTo
    {
        return $this->belongsTo(Tenant::class);
    }

    public function scopeWithoutTenantScope(Builder $query): Builder
    {
        return $query->withoutGlobalScope('tenant');
    }

    public function scopeTenant(Builder $query): void
    {
        $tenantManager = app(TenantManager::class);

        if (! $tenantManager->hasContext()) {
            return;
        }

        $query->where(
            $query->getModel()->getTable().'.tenant_id',
            $tenantManager->getCurrentTenantId()
        );
    }
}
