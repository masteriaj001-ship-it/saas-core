<?php

declare(strict_types=1);

namespace App\Services\Auth;

use App\Models\Category;
use App\Models\Contact;
use App\Models\Item;
use App\Models\Location;
use App\Models\ModuleCatalog;
use App\Models\Tenant;
use App\Models\TenantModule;
use App\Models\User;
use App\Modules\Plataforma\Models\Plan;
use App\Modules\Plataforma\Models\Subscription;
use App\Modules\Talleres\Models\Asset;
use App\Services\TenantManager;
use Database\Seeders\RolePermissionSeeder;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

class RegisterService
{
    public function __construct(
        private readonly TenantManager $tenantManager,
    ) {}

    public function register(array $data): User
    {
        return DB::transaction(function () use ($data) {
            $slug = Str::of($data['business_name'])
                ->slug()
                ->append('-', Str::random(4));

            $tenant = Tenant::create([
                'name' => $data['business_name'],
                'slug' => $slug,
                'is_active' => true,
                'settings' => [],
            ]);

            $this->createFreeSubscription($tenant);

            $this->tenantManager->setTenantContext($tenant->id);

            $user = User::create([
                'name' => $data['name'],
                'email' => $data['email'],
                'password' => $data['password'],
            ]);

            $seeder = app(RolePermissionSeeder::class);
            $seeder->run();

            $user->assignRole('owner');

            $this->createDefaults();

            Auth::login($user);

            $this->tenantManager->clearTenantContext();

            return $user;
        });
    }

    private function createDefaults(): void
    {
        Location::create([
            'name' => 'Sede Principal',
            'is_main' => true,
            'is_active' => true,
        ]);

        $defaults = config('industry-defaults.industries.general', []);

        foreach ($defaults['categories'] as $catName) {
            Category::create(['name' => $catName]);
        }

        foreach ($defaults['items'] as $itemData) {
            Item::create([
                'sku' => $itemData['sku'].'-'.Str::random(4),
                'name' => $itemData['name'],
                'item_type' => $itemData['item_type'],
                'price' => $itemData['price'],
            ]);
        }

        foreach ($defaults['assets'] ?? [] as $assetData) {
            Asset::create([
                'name' => $assetData['name'],
                'asset_type' => $assetData['asset_type'],
                'status' => $assetData['status'],
            ]);
        }

        Contact::create([
            'name' => 'Cliente Ejemplo',
            'contact_type' => 'client',
        ]);

        Contact::create([
            'name' => 'Proveedor Ejemplo',
            'contact_type' => 'supplier',
        ]);

        $moduleSlugs = ['inventory', 'transactions', 'contacts'];
        $modules = ModuleCatalog::whereIn('slug', $moduleSlugs)->pluck('slug');

        foreach ($modules as $moduleSlug) {
            TenantModule::create([
                'module_slug' => $moduleSlug,
                'is_active' => true,
                'activated_at' => now(),
            ]);
        }
    }

    private function createFreeSubscription(Tenant $tenant): void
    {
        $freePlan = Plan::where('name', 'free')->first();

        if ($freePlan) {
            Subscription::create([
                'tenant_id' => $tenant->id,
                'plan_id' => $freePlan->id,
                'started_at' => now(),
                'status' => 'active',
            ]);
        }
    }
}
