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

class AssetTallerTest extends TestCase
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

    public function test_admin_can_create_asset_with_plate_brand_model_year(): void
    {
        $asset = Asset::create([
            'tenant_id' => $this->tenant->id,
            'name' => 'Toyota Corolla',
            'code' => 'ASSET-001',
            'plate' => 'ABC-123',
            'brand' => 'Toyota',
            'model' => 'Corolla',
            'year' => 2020,
            'asset_type' => 'vehicles',
        ]);

        $this->assertDatabaseHas('assets', [
            'id' => $asset->id,
            'plate' => 'ABC-123',
            'brand' => 'Toyota',
            'model' => 'Corolla',
            'year' => 2020,
        ]);
    }

    public function test_duplicate_plate_within_same_tenant_raises_exception(): void
    {
        Asset::create([
            'tenant_id' => $this->tenant->id,
            'name' => 'Toyota Corolla',
            'code' => 'ASSET-001',
            'plate' => 'ABC-123',
            'brand' => 'Toyota',
            'model' => 'Corolla',
            'year' => 2020,
            'asset_type' => 'vehicles',
        ]);

        $this->assertDatabaseHas('assets', ['plate' => 'ABC-123']);

        $this->expectException(QueryException::class);
        $this->expectExceptionMessageMatches('/unique|duplicate/i');

        Asset::create([
            'tenant_id' => $this->tenant->id,
            'name' => 'Honda Civic',
            'code' => 'ASSET-002',
            'plate' => 'ABC-123',
            'brand' => 'Honda',
            'model' => 'Civic',
            'year' => 2021,
            'asset_type' => 'vehicles',
        ]);
    }

    public function test_same_plate_allowed_in_different_tenants(): void
    {
        $tenantB = Tenant::factory()->create();

        Asset::create([
            'tenant_id' => $this->tenant->id,
            'name' => 'Nissan Versa',
            'code' => 'ASSET-001',
            'plate' => 'XYZ-789',
            'brand' => 'Nissan',
            'model' => 'Versa',
            'year' => 2022,
            'asset_type' => 'vehicles',
        ]);

        app(TenantManager::class)->setTenantContext($tenantB->id);

        $assetB = Asset::create([
            'name' => 'Mazda 3',
            'code' => 'ASSET-002',
            'plate' => 'XYZ-789',
            'brand' => 'Mazda',
            'model' => '3',
            'year' => 2023,
            'asset_type' => 'vehicles',
        ]);

        $this->assertDatabaseHas('assets', ['id' => $assetB->id, 'plate' => 'XYZ-789']);
    }

    public function test_can_create_asset_with_vin(): void
    {
        $owner = Contact::factory()->for($this->tenant)->client()->create();

        $asset = Asset::create([
            'tenant_id' => $this->tenant->id,
            'name' => 'Ford Mustang',
            'code' => 'ASSET-003',
            'vin' => '1FA6P8CF7L1234567',
            'plate' => 'FORD-001',
            'brand' => 'Ford',
            'model' => 'Mustang',
            'year' => 2024,
            'asset_type' => 'vehicles',
            'owner_id' => $owner->id,
        ]);

        $this->assertDatabaseHas('assets', [
            'id' => $asset->id,
            'vin' => '1FA6P8CF7L1234567',
            'owner_id' => $owner->id,
        ]);
        $this->assertInstanceOf(Contact::class, $asset->owner);
        $this->assertTrue($asset->owner->is($owner));
    }

    public function test_vin_unique_per_tenant(): void
    {
        $owner = Contact::factory()->for($this->tenant)->client()->create();

        Asset::create([
            'tenant_id' => $this->tenant->id,
            'name' => 'Ford Mustang',
            'code' => 'ASSET-003',
            'vin' => '1FA6P8CF7L1234567',
            'plate' => 'FORD-001',
            'brand' => 'Ford',
            'model' => 'Mustang',
            'year' => 2024,
            'asset_type' => 'vehicles',
            'owner_id' => $owner->id,
        ]);

        $this->assertDatabaseHas('assets', ['vin' => '1FA6P8CF7L1234567']);

        $this->expectException(QueryException::class);
        $this->expectExceptionMessageMatches('/unique|duplicate/i');

        Asset::create([
            'tenant_id' => $this->tenant->id,
            'name' => 'Honda Civic',
            'code' => 'ASSET-004',
            'vin' => '1FA6P8CF7L1234567',
            'plate' => 'HONDA-001',
            'brand' => 'Honda',
            'model' => 'Civic',
            'year' => 2024,
            'asset_type' => 'vehicles',
            'owner_id' => $owner->id,
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
            'code' => 'ASSET-003',
            'vin' => '1FA6P8CF7L1234567',
            'plate' => 'FORD-001',
            'brand' => 'Ford',
            'model' => 'Mustang',
            'year' => 2024,
            'asset_type' => 'vehicles',
            'owner_id' => $ownerA->id,
        ]);

        app(TenantManager::class)->setTenantContext($tenantB->id);

        $assetB = Asset::create([
            'tenant_id' => $tenantB->id,
            'name' => 'Fiat 500',
            'code' => 'ASSET-099',
            'vin' => '1FA6P8CF7L1234567',
            'plate' => 'FIAT-001',
            'brand' => 'Fiat',
            'model' => '500',
            'year' => 2024,
            'asset_type' => 'vehicles',
            'owner_id' => $ownerB->id,
        ]);

        $this->assertDatabaseHas('assets', ['id' => $assetB->id, 'vin' => '1FA6P8CF7L1234567']);
    }

    public function test_owner_relationship_returns_contact(): void
    {
        $owner = Contact::factory()->for($this->tenant)->client()->create();

        $asset = Asset::create([
            'tenant_id' => $this->tenant->id,
            'name' => 'Nissan Tsuru',
            'code' => 'ASSET-005',
            'vin' => '3N1AB51D2XL123456',
            'plate' => 'NISS-001',
            'brand' => 'Nissan',
            'model' => 'Tsuru',
            'year' => 2010,
            'asset_type' => 'vehicles',
            'owner_id' => $owner->id,
        ]);

        $this->assertInstanceOf(Contact::class, $asset->owner);
        $this->assertEquals($owner->id, $asset->owner->id);
        $this->assertEquals($owner->name, $asset->owner->name);
    }
}
