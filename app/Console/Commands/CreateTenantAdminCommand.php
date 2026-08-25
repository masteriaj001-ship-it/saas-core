<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Models\Tenant;
use App\Models\User;
use App\Modules\Plataforma\Models\Plan;
use App\Modules\Plataforma\Models\Subscription;
use App\Services\TenantManager;
use Database\Seeders\RolePermissionSeeder;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;

class CreateTenantAdminCommand extends Command
{
    protected $signature = 'jaosoft:create-tenant-admin';

    protected $description = 'Create a tenant with its admin user (transactional)';

    public function handle(TenantManager $tenantManager): int
    {
        $companyName = $this->ask('Nombre de la Empresa/Taller');
        $slug = $this->ask('Slug del Tenant');
        $adminName = $this->ask('Nombre del Administrador');
        $adminEmail = $this->ask('Email del Administrador');
        $password = $this->secret('Contraseña');

        if (Tenant::where('slug', $slug)->exists()) {
            $this->error("Ya existe un tenant con el slug {$slug}.");

            return Command::FAILURE;
        }

        if (User::where('email', $adminEmail)->exists()) {
            $this->error("Ya existe un usuario con el email {$adminEmail}.");

            return Command::FAILURE;
        }

        DB::beginTransaction();

        try {
            $tenant = Tenant::create([
                'name' => $companyName,
                'slug' => $slug,
                'is_active' => true,
                'settings' => '{}',
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

            $tenantManager->setTenantContext($tenant->id);

            $user = User::create([
                'name' => $adminName,
                'email' => $adminEmail,
                'password' => Hash::make($password),
            ]);

            if (empty($user->tenant_id)) {
                throw new \RuntimeException(
                    'BelongsToTenant failed to inject tenant_id for user: '.$adminEmail
                );
            }

            $this->callSilent(RolePermissionSeeder::class);

            $user->assignRole('owner');

            $tenantManager->clearTenantContext();

            DB::commit();
        } catch (\Throwable $e) {
            DB::rollBack();
            $this->error("Error: {$e->getMessage()}");

            return Command::FAILURE;
        }

        $this->info("Tenant '{$companyName}' creado con admin {$adminEmail}.");

        return Command::SUCCESS;
    }
}
