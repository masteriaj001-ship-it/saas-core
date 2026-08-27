<?php

declare(strict_types=1);

namespace Tests\Feature\Talleres;

use App\Models\Location;
use App\Models\Tenant;
use App\Models\User;
use App\Modules\Talleres\Models\WorkshopBay;
use App\Services\TenantManager;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

final class WorkshopBayTest extends TestCase
{
    use RefreshDatabase;

    private Tenant $tenant;

    private User $user;

    protected function setUp(): void
    {
        parent::setUp();

        $this->tenant = Tenant::factory()->create(['onboarding_completed' => true]);
        $this->user = User::factory()->for($this->tenant)->create();

        $this->actingAs($this->user);
        app(TenantManager::class)->setTenantContext($this->tenant->id);
    }

    public function test_workshop_bay_can_be_created(): void
    {
        $bay = WorkshopBay::factory()->create([
            'tenant_id' => $this->tenant->id,
        ]);

        $this->assertDatabaseHas('workshop_bays', [
            'id' => $bay->id,
            'tenant_id' => $this->tenant->id,
        ]);
    }

    public function test_workshop_bay_has_location(): void
    {
        $location = Location::factory()->create([
            'tenant_id' => $this->tenant->id,
        ]);

        $bay = WorkshopBay::factory()->create([
            'tenant_id' => $this->tenant->id,
            'location_id' => $location->id,
        ]);

        $this->assertNotNull($bay->location);
        $this->assertEquals($location->id, $bay->location->id);
    }

    public function test_workshop_bay_types(): void
    {
        $types = ['standard', 'lift', 'paint', 'diagnostic'];

        foreach ($types as $type) {
            $bay = WorkshopBay::factory()->create([
                'tenant_id' => $this->tenant->id,
                'type' => $type,
            ]);

            $this->assertEquals($type, $bay->type);
        }
    }
}
