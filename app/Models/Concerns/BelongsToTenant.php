<?php

declare(strict_types=1);

namespace App\Models\Concerns;

use App\Models\Tenant;
use App\Services\TenantManager;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Facades\Auth;
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

                if ($tenantManager->hasContext()) {
                    $model->tenant_id = $tenantManager->getCurrentTenantId();

                    return;
                }

                $user = Auth::user();

                if ($user && ! empty($user->tenant_id)) {
                    $model->tenant_id = $user->tenant_id;

                    return;
                }

                throw new RuntimeException(
                    'Cannot create '.static::class.' without tenant context or authenticated user.'
                );
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
