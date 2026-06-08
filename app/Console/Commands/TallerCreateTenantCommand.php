<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Models\Tenant;
use App\Models\User;
use App\Services\TenantManager;
use Database\Seeders\RolePermissionSeeder;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Spatie\Permission\PermissionRegistrar;

class TallerCreateTenantCommand extends Command
{
    protected $signature = 'taller:create-tenant
                            {--name= : Tenant company name}
                            {--slug= : Tenant URL slug}
                            {--email= : Admin user email}
                            {--password= : Admin user password}';

    protected $description = 'Create a new tenant with admin user, roles, and permissions';

    public function handle(TenantManager $tenantManager): int
    {
        $name = $this->option('name');
        $slug = $this->option('slug');
        $email = $this->option('email');
        $password = $this->option('password');

        if (Tenant::where('slug', $slug)->exists()) {
            $this->error("Tenant with slug '{$slug}' already exists.");

            return Command::FAILURE;
        }

        if (User::where('email', $email)->exists()) {
            $this->error("User with email '{$email}' already exists.");

            return Command::FAILURE;
        }

        DB::transaction(function () use ($name, $slug, $email, $password, $tenantManager): void {
            $tenant = Tenant::create([
                'name' => $name,
                'slug' => $slug,
                'plan' => 'basic',
                'is_active' => true,
                'settings' => '{}',
            ]);

            $tenantManager->setTenantContext($tenant->id);

            app(PermissionRegistrar::class)->forgetCachedPermissions();

            app(RolePermissionSeeder::class)->run();

            $user = User::create([
                'name' => $name,
                'email' => $email,
                'password' => Hash::make($password),
                'tenant_id' => $tenant->id,
            ]);

            $user->assignRole('owner');
        });

        $this->info("Tenant '{$name}' created with admin {$email}.");

        return Command::SUCCESS;
    }
}
