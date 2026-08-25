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
use Illuminate\Support\Facades\Hash;

class CreateTenantCommand extends Command
{
    protected $signature = 'tenant:create {name : Tenant name} {slug : Tenant slug} {email? : Admin email}';

    protected $description = 'Create a new tenant with admin user and base roles/permissions';

    public function handle(TenantManager $tenantManager): int
    {
        $name = $this->argument('name');
        $slug = $this->argument('slug');
        $email = $this->argument('email') ?? "admin@{$slug}.test";

        $tenant = Tenant::create([
            'name' => $name,
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

        $this->info("Tenant created: {$tenant->id}");

        $tenantManager->setTenantContext($tenant->id);

        $user = User::create([
            'name' => $name.' Admin',
            'email' => $email,
            'password' => Hash::make('secret123'),
        ]);

        $this->info("Admin user created: {$user->email} / secret123");

        $this->callSilent(RolePermissionSeeder::class);

        $user->assignRole('owner');

        $this->info('Base roles and permissions seeded.');

        $tenantManager->clearTenantContext();

        $this->info('Done.');

        return Command::SUCCESS;
    }
}
