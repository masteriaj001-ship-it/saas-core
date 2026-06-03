<?php

declare(strict_types=1);

namespace App\Services;

use App\Modules\Talleres\Models\Asset;
use App\Models\Category;
use App\Models\Item;
use App\Models\Tenant;
use Illuminate\Support\Str;

class TenantTemplateSeeder
{
    public function __construct(
        private readonly TenantManager $tenantManager,
    ) {}

    public function seed(Tenant $tenant, string $industry): void
    {
        $this->tenantManager->setTenantContext($tenant->id);

        $defaults = config("industry-defaults.industries.{$industry}", config('industry-defaults.industries.general'));

        foreach ($defaults['categories'] as $catName) {
            Category::firstOrCreate(['name' => $catName]);
        }

        foreach ($defaults['items'] as $itemData) {
            Item::firstOrCreate(
                ['sku' => $itemData['sku'].'-'.Str::random(4)],
                [
                    'name' => $itemData['name'],
                    'item_type' => $itemData['item_type'],
                    'price' => $itemData['price'],
                ]
            );
        }

        foreach ($defaults['assets'] ?? [] as $assetData) {
            Asset::firstOrCreate(
                ['name' => $assetData['name']],
                [
                    'asset_type' => $assetData['asset_type'],
                    'status' => $assetData['status'],
                ]
            );
        }

        $tenant->update(['onboarding_completed' => true]);

        $this->tenantManager->clearTenantContext();
    }
}
