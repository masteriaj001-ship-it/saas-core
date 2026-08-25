<?php

declare(strict_types=1);

namespace App\Filament\Superadmin\Resources\TenantResource\Pages;

use App\Filament\Superadmin\Resources\TenantResource;
use App\Models\Tenant;
use App\Models\User;
use App\Modules\Plataforma\Models\Plan;
use App\Modules\Plataforma\Models\Subscription;
use App\Services\TenantManager;
use App\Services\TenantTemplateSeeder;
use Database\Seeders\RolePermissionSeeder;
use Filament\Resources\Pages\CreateRecord;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Spatie\Permission\PermissionRegistrar;

class CreateTenant extends CreateRecord
{
    protected static string $resource = TenantResource::class;

    protected function handleRecordCreation(array $data): Model
    {
        return DB::transaction(function () use ($data) {
            $tenant = Tenant::create([
                'name' => $data['name'],
                'slug' => $data['slug'],
                'is_active' => $data['is_active'],
                'settings' => $data['settings'] ?? [],
            ]);

            $freePlan = Plan::where('name', 'free')->first();

            if ($freePlan) {
                Subscription::create([
                    'tenant_id' => $tenant->id,
                    'plan_id' => $freePlan->id,
                    'started_at' => now(),
                    'status' => 'active',
                ]);
            }

            app(TenantManager::class)->setTenantContext($tenant->id);

            app(PermissionRegistrar::class)->forgetCachedPermissions();

            app(RolePermissionSeeder::class)->run();

            $user = User::create([
                'name' => $data['admin_name'],
                'email' => $data['admin_email'],
                'password' => Hash::make($data['admin_password']),
                'tenant_id' => $tenant->id,
            ]);

            $user->assignRole('owner');

            app(TenantTemplateSeeder::class)->seed($tenant);

            return $tenant;
        });
    }

    protected function afterCreate(): void
    {
        app(TenantManager::class)->clearTenantContext();
    }
}
