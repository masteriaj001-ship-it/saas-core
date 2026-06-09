<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Models\Location;
use App\Models\Tenant;
use App\Models\User;
use App\Services\TenantManager;
use Illuminate\Database\QueryException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class LocationResourceTest extends TestCase
{
    use RefreshDatabase;

    private Tenant $tenant;

    private User $user;

    protected function setUp(): void
    {
        parent::setUp();

        $this->tenant = Tenant::factory()->create();
        $this->user = User::factory()->for($this->tenant)->create();

        $this->actingAs($this->user);
        app(TenantManager::class)->setTenantContext($this->tenant->id);
    }

    public function test_can_create_location(): void
    {
        $location = Location::create([
            'tenant_id' => $this->tenant->id,
            'name' => 'Sede Principal',
            'address' => 'Calle 123 #45-67',
            'is_main' => true,
            'is_active' => true,
        ]);

        $this->assertDatabaseHas('locations', [
            'id' => $location->id,
            'tenant_id' => $this->tenant->id,
            'name' => 'Sede Principal',
            'address' => 'Calle 123 #45-67',
            'is_main' => true,
            'is_active' => true,
        ]);
    }

    public function test_can_update_location(): void
    {
        $location = Location::factory()->create([
            'tenant_id' => $this->tenant->id,
        ]);

        $location->update([
            'name' => 'Sede Actualizada',
            'address' => 'Nueva dirección',
        ]);

        $this->assertDatabaseHas('locations', [
            'id' => $location->id,
            'name' => 'Sede Actualizada',
            'address' => 'Nueva dirección',
        ]);
    }

    public function test_can_soft_delete_location(): void
    {
        $location = Location::factory()->create([
            'tenant_id' => $this->tenant->id,
        ]);

        $location->delete();

        $this->assertSoftDeleted($location);
    }

    public function test_name_is_required(): void
    {
        $this->expectException(QueryException::class);

        Location::create([
            'tenant_id' => $this->tenant->id,
            'name' => null,
        ]);
    }

    public function test_tenant_isolation(): void
    {
        $location = Location::factory()->create([
            'name' => 'Sede Secreta',
            'tenant_id' => $this->tenant->id,
        ]);

        $otherTenant = Tenant::factory()->create();
        $otherLocation = Location::factory()->create([
            'name' => 'Sede del Otro',
            'tenant_id' => $otherTenant->id,
        ]);

        $visibleIds = Location::pluck('id')->toArray();

        $this->assertContains($location->id, $visibleIds);
        $this->assertNotContains($otherLocation->id, $visibleIds);
    }

    public function test_default_values(): void
    {
        $location = Location::create([
            'tenant_id' => $this->tenant->id,
            'name' => 'Sede Test',
        ]);

        $location->refresh();

        $this->assertFalse($location->is_main);
        $this->assertTrue($location->is_active);
    }

    public function test_can_mark_as_main(): void
    {
        $location = Location::factory()->main()->create([
            'tenant_id' => $this->tenant->id,
        ]);

        $this->assertTrue($location->is_main);
    }

    public function test_name_max_length(): void
    {
        $longName = str_repeat('A', 256);

        $this->expectException(QueryException::class);

        Location::create([
            'tenant_id' => $this->tenant->id,
            'name' => $longName,
        ]);
    }
}
