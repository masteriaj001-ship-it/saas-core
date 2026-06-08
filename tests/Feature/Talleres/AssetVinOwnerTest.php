<?php

declare(strict_types=1);

namespace Tests\Feature\Talleres;

use App\Models\Contact;
use App\Models\Tenant;
use App\Models\User;
use App\Modules\Talleres\Models\Asset;
use App\Services\TenantManager;
use Illuminate\Database\QueryException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class AssetVinOwnerTest extends TestCase
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

    public function test_can_create_asset_with_vin_and_owner(): void
    {
        $owner = Contact::factory()->for($this->tenant)->client()->create();

        $asset = Asset::create([
            'tenant_id' => $this->tenant->id,
            'name' => 'Ford Mustang',
            'code' => 'ASSET-VIN-001',
            'vin' => '1FA6P8CF7L1234567',
            'plate' => 'VIN-001',
            'brand' => 'Ford',
            'model' => 'Mustang',
            'year' => 2024,
            'asset_type' => 'vehicle',
            'owner_contact_id' => $owner->id,
        ]);

        $this->assertDatabaseHas('assets', [
            'id' => $asset->id,
            'vin' => '1FA6P8CF7L1234567',
            'owner_contact_id' => $owner->id,
        ]);
    }

    public function test_vin_unique_per_tenant(): void
    {
        $owner = Contact::factory()->for($this->tenant)->client()->create();

        Asset::create([
            'tenant_id' => $this->tenant->id,
            'name' => 'Ford Mustang',
            'code' => 'ASSET-VIN-001',
            'vin' => '1FA6P8CF7L1234567',
            'plate' => 'VIN-001',
            'brand' => 'Ford',
            'model' => 'Mustang',
            'year' => 2024,
            'asset_type' => 'vehicle',
            'owner_contact_id' => $owner->id,
        ]);

        $this->expectException(QueryException::class);
        $this->expectExceptionMessageMatches('/unique|duplicate/i');

        Asset::create([
            'tenant_id' => $this->tenant->id,
            'name' => 'Honda Civic',
            'code' => 'ASSET-VIN-002',
            'vin' => '1FA6P8CF7L1234567',
            'plate' => 'VIN-002',
            'brand' => 'Honda',
            'model' => 'Civic',
            'year' => 2024,
            'asset_type' => 'vehicle',
            'owner_contact_id' => $owner->id,
        ]);
    }

    public function test_same_vin_allowed_different_tenants(): void
    {
        $ownerA = Contact::factory()->for($this->tenant)->client()->create();
        $tenantB = Tenant::factory()->create();
        $ownerB = Contact::factory()->for($tenantB)->client()->create();

        Asset::create([
            'tenant_id' => $this->tenant->id,
            'name' => 'Ford Mustang',
            'code' => 'ASSET-VIN-001',
            'vin' => '1FA6P8CF7L1234567',
            'plate' => 'VIN-001',
            'brand' => 'Ford',
            'model' => 'Mustang',
            'year' => 2024,
            'asset_type' => 'vehicle',
            'owner_contact_id' => $ownerA->id,
        ]);

        app(TenantManager::class)->setTenantContext($tenantB->id);

        $assetB = Asset::create([
            'tenant_id' => $tenantB->id,
            'name' => 'Fiat 500',
            'code' => 'ASSET-VIN-099',
            'vin' => '1FA6P8CF7L1234567',
            'plate' => 'VIN-099',
            'brand' => 'Fiat',
            'model' => '500',
            'year' => 2024,
            'asset_type' => 'vehicle',
            'owner_contact_id' => $ownerB->id,
        ]);

        $this->assertDatabaseHas('assets', ['id' => $assetB->id, 'vin' => '1FA6P8CF7L1234567']);
    }

    public function test_owner_relationship_returns_contact(): void
    {
        $owner = Contact::factory()->for($this->tenant)->client()->create();

        $asset = Asset::create([
            'tenant_id' => $this->tenant->id,
            'name' => 'Nissan Tsuru',
            'code' => 'ASSET-VIN-003',
            'vin' => '3N1AB51D2XL123456',
            'plate' => 'VIN-003',
            'brand' => 'Nissan',
            'model' => 'Tsuru',
            'year' => 2010,
            'asset_type' => 'vehicle',
            'owner_contact_id' => $owner->id,
        ]);

        $this->assertInstanceOf(Contact::class, $asset->owner);
        $this->assertEquals($owner->id, $asset->owner->id);
        $this->assertEquals($owner->name, $asset->owner->name);
    }
}
