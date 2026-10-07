<?php

declare(strict_types=1);

namespace Tests\Feature\Inventario;

use App\Models\Item;
use App\Models\Tenant;
use App\Models\User;
use App\Modules\Inventario\Notifications\LowStockNotification;
use App\Services\TenantManager;
use Database\Seeders\RolePermissionSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Notification;
use Tests\TestCase;

final class CheckLowStockCommandTest extends TestCase
{
    use RefreshDatabase;

    private Tenant $tenant;

    private User $user;

    protected function setUp(): void
    {
        parent::setUp();

        $this->tenant = Tenant::factory()->create(['onboarding_completed' => true]);
        $this->user = User::factory()->for($this->tenant)->create();

        app(TenantManager::class)->setTenantContext($this->tenant->id);
        $this->seed(RolePermissionSeeder::class);

        $this->user->assignRole('owner');

        $this->actingAs($this->user);
    }

    public function test_command_runs_successfully(): void
    {
        $this->artisan('inventory:check-low-stock')
            ->expectsOutput('No items with low stock found.')
            ->assertExitCode(0);
    }

    public function test_command_detects_low_stock_items(): void
    {
        Item::factory()->create([
            'tenant_id' => $this->tenant->id,
            'stock' => 2,
            'min_stock' => 10,
        ]);

        Notification::fake();

        $this->artisan('inventory:check-low-stock')
            ->assertExitCode(0);

        Notification::assertSentTo(
            [$this->user],
            LowStockNotification::class,
        );
    }

    public function test_command_does_not_notify_when_stock_is_ok(): void
    {
        Item::factory()->create([
            'tenant_id' => $this->tenant->id,
            'stock' => 50,
            'min_stock' => 10,
        ]);

        Notification::fake();

        $this->artisan('inventory:check-low-stock')
            ->expectsOutput('No items with low stock found.')
            ->assertExitCode(0);

        Notification::assertNothingSent();
    }

    public function test_command_can_filter_by_tenant(): void
    {
        $otherTenant = Tenant::factory()->create(['onboarding_completed' => true]);

        Item::factory()->create([
            'tenant_id' => $this->tenant->id,
            'stock' => 2,
            'min_stock' => 10,
        ]);

        Item::factory()->create([
            'tenant_id' => $otherTenant->id,
            'stock' => 2,
            'min_stock' => 10,
        ]);

        Notification::fake();

        $this->artisan('inventory:check-low-stock', ['--tenant' => $this->tenant->id])
            ->assertExitCode(0);

        Notification::assertSentTo(
            [$this->user],
            LowStockNotification::class,
        );
    }

    public function test_command_notifies_all_tenants_without_flag(): void
    {
        $otherTenant = Tenant::factory()->create(['onboarding_completed' => true]);
        $otherUser = User::factory()->for($otherTenant)->create();

        app(TenantManager::class)->setTenantContext($otherTenant->id);
        $this->seed(RolePermissionSeeder::class);
        $otherUser->assignRole('owner');
        app(TenantManager::class)->setTenantContext($this->tenant->id);

        Item::factory()->create([
            'tenant_id' => $this->tenant->id,
            'stock' => 2,
            'min_stock' => 10,
        ]);

        Item::factory()->create([
            'tenant_id' => $otherTenant->id,
            'stock' => 1,
            'min_stock' => 10,
        ]);

        Notification::fake();

        $this->artisan('inventory:check-low-stock')
            ->assertExitCode(0);

        Notification::assertSentTo(
            [$this->user],
            LowStockNotification::class,
            fn (LowStockNotification $notification): bool => ($notification->tenantId ?? null) === $this->tenant->id
        );

        Notification::assertSentTo(
            [$otherUser],
            LowStockNotification::class,
            fn (LowStockNotification $notification): bool => ($notification->tenantId ?? null) === $otherTenant->id
        );
    }
}
