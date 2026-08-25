<?php

declare(strict_types=1);

namespace App\Modules\Talleres\Actions;

use App\Models\Tenant;
use App\Models\User;
use App\Modules\Plataforma\Models\Plan;
use App\Modules\Plataforma\Models\Subscription;
use App\Modules\Talleres\Exceptions\TenantRegistrationException;
use App\Services\TenantManager;
use Database\Seeders\RolePermissionSeeder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Spatie\Permission\PermissionRegistrar;

final class RegisterTenantAction
{
    public function __construct(
        private readonly TenantManager $tenantManager,
    ) {}

    public function execute(array $data): User
    {
        $this->validate($data);

        return DB::transaction(function () use ($data) {
            $slug = $this->generateSlug($data['business_name']);

            $settings = is_array($data['settings'] ?? null)
                ? $data['settings']
                : [];

            $tenant = Tenant::create([
                'name' => $data['business_name'],
                'slug' => $slug,
                'is_active' => true,
                'settings' => $settings,
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

            $this->tenantManager->setTenantContext($tenant->id);

            $user = User::create([
                'name' => $data['name'],
                'email' => $data['email'],
                'password' => $data['password'],
                'tenant_id' => $tenant->id,
            ]);

            app(PermissionRegistrar::class)->forgetCachedPermissions();

            app(RolePermissionSeeder::class)->run();

            $user->assignRole('owner');

            $this->tenantManager->clearTenantContext();

            return $user;
        });
    }

    private function validate(array $data): void
    {
        if (empty($data['email'])) {
            throw TenantRegistrationException::registrationFailed('Email is required.');
        }

        if (empty($data['password'])) {
            throw TenantRegistrationException::registrationFailed('Password is required.');
        }

        if (empty($data['business_name'])) {
            throw TenantRegistrationException::registrationFailed('Business name is required.');
        }

        if (User::where('email', $data['email'])->exists()) {
            throw TenantRegistrationException::duplicateEmail($data['email']);
        }

        if (! empty($data['slug']) && Tenant::where('slug', $data['slug'])->exists()) {
            throw TenantRegistrationException::duplicateSlug($data['slug']);
        }
    }

    private function generateSlug(string $businessName): string
    {
        $base = Str::of($businessName)->slug()->toString();
        $slug = $base;

        while (Tenant::where('slug', $slug)->exists()) {
            $slug = $base.'-'.Str::random(4);
        }

        return $slug;
    }
}
