<?php

declare(strict_types=1);

namespace App\Modules\Talleres\Actions;

use App\Modules\Talleres\Models\Asset;
use App\Services\TenantManager;
use Illuminate\Validation\ValidationException;

final class CreateAssetAction
{
    public function __construct(
        private readonly TenantManager $tenantManager,
    ) {}

    public function execute(array $data): Asset
    {
        $tenantId = $this->tenantManager->getCurrentTenantId();

        if (! empty($data['plate'])) {
            $exists = Asset::where('tenant_id', $tenantId)
                ->where('plate', $data['plate'])
                ->exists();

            if ($exists) {
                throw ValidationException::withMessages([
                    'plate' => __('Ya existe un activo con esa placa en este tenant.'),
                ]);
            }
        }

        return Asset::create($data);
    }
}
