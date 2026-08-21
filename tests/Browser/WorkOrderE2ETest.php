<?php

declare(strict_types=1);

namespace Tests\Browser;

use App\Models\Contact;
use App\Models\Tenant;
use App\Models\User;
use App\Modules\Talleres\Models\ClientVehicle;
use App\Modules\Talleres\Models\ServiceCatalog;
use App\Modules\Talleres\Models\WorkOrder;
use App\Modules\Talleres\Models\WorkOrderItem;
use Illuminate\Foundation\Testing\DatabaseMigrations;
use Illuminate\Support\Facades\Hash;
use Laravel\Dusk\Browser;
use Tests\DuskTestCase;

class WorkOrderE2ETest extends DuskTestCase
{
    use DatabaseMigrations;

    public function test_user_can_create_work_order_via_wizard(): void
    {
        $tenant = Tenant::factory()->create([
            'onboarding_completed' => true,
        ]);

        $user = User::factory()->for($tenant)->create([
            'email' => 'admin@test.com',
            'password' => Hash::make('password'),
        ]);

        ClientVehicle::factory()->for($tenant)->create([
            'brand' => 'Toyota',
            'model' => 'Corolla',
            'plate' => 'ABC-123',
        ]);

        $this->browse(function (Browser $browser) use ($tenant, $user) {
            $browser->loginAs($user)
                ->visit('/admin/'.$tenant->slug.'/work-orders/create')
                ->waitForText('Recepción')
                ->press('Next')
                ->waitForText('Diagnóstico')
                ->type('data.title', 'Mantenimiento preventivo')
                ->press('Next')
                ->waitForText('Cierre')
                ->press('Create')
                ->waitForLocation('/admin/'.$tenant->slug.'/work-orders', 10)
                ->assertSee('Mantenimiento preventivo');
        });
    }

    public function test_status_change_saves_successfully(): void
    {
        $tenant = Tenant::factory()->create([
            'onboarding_completed' => true,
        ]);

        $user = User::factory()->for($tenant)->create([
            'email' => 'admin@test.com',
            'password' => Hash::make('password'),
        ]);

        $clientVehicle = ClientVehicle::factory()->for($tenant)->create();

        $contact = Contact::factory()->for($tenant)->client()->create();

        $workOrder = WorkOrder::factory()->create([
            'tenant_id' => $tenant->id,
            'client_vehicle_id' => $clientVehicle->id,
            'contact_id' => $contact->id,
            'title' => 'Cambio de frenos',
            'status' => 'received',
        ]);

        $this->browse(function (Browser $browser) use ($tenant, $workOrder, $user) {
            $browser->loginAs($user)
                ->visit("/admin/{$tenant->slug}/work-orders/{$workOrder->id}/edit")
                ->waitForText('Cambio de frenos')
                ->select('data.status', 'diagnosing')
                ->press('Save')
                ->waitForText('Saved');
        });
    }

    public function test_repeater_shows_service_catalog_name(): void
    {
        $tenant = Tenant::factory()->create([
            'onboarding_completed' => true,
        ]);

        $user = User::factory()->for($tenant)->create([
            'email' => 'admin@test.com',
            'password' => Hash::make('password'),
        ]);

        $clientVehicle = ClientVehicle::factory()->for($tenant)->create();

        $contact = Contact::factory()->for($tenant)->client()->create();

        $workOrder = WorkOrder::factory()->create([
            'tenant_id' => $tenant->id,
            'client_vehicle_id' => $clientVehicle->id,
            'contact_id' => $contact->id,
            'title' => 'WO con servicio',
            'status' => 'received',
        ]);

        $serviceCatalog = ServiceCatalog::factory()->create([
            'tenant_id' => $tenant->id,
            'name' => 'Cambio de aceite',
        ]);

        WorkOrderItem::factory()->create([
            'work_order_id' => $workOrder->id,
            'tenant_id' => $tenant->id,
            'type' => 'service',
            'item_id' => null,
            'service_catalog_id' => $serviceCatalog->id,
        ]);

        $this->browse(function (Browser $browser) use ($tenant, $workOrder, $user) {
            $browser->loginAs($user)
                ->visit("/admin/{$tenant->slug}/work-orders/{$workOrder->id}/edit")
                ->waitForText('WO con servicio')
                ->clickLink('Items')
                ->waitForText('Cambio de aceite')
                ->assertSee('Cambio de aceite');
        });
    }
}
