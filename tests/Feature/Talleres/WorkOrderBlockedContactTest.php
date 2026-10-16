<?php

declare(strict_types=1);

namespace Tests\Feature\Talleres;

use App\Filament\Resources\WorkOrderResource\Pages\CreateWorkOrder;
use App\Filament\Resources\WorkOrderResource\Pages\EditWorkOrder;
use App\Models\Contact;
use App\Models\Organization;
use App\Models\Tenant;
use App\Models\User;
use App\Modules\Talleres\Actions\CreateWorkOrderReceptionAction;
use App\Modules\Talleres\Models\ClientVehicle;
use App\Modules\Talleres\Models\WorkOrder;
use App\Services\TenantManager;
use Database\Seeders\RolePermissionSeeder;
use Filament\Facades\Filament;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Validation\ValidationException;
use Livewire\Livewire;
use Spatie\Permission\PermissionRegistrar;
use Tests\TestCase;

final class WorkOrderBlockedContactTest extends TestCase
{
    use RefreshDatabase;

    private Tenant $tenant;

    private User $user;

    private ClientVehicle $clientVehicle;

    protected function setUp(): void
    {
        parent::setUp();

        $org = Organization::factory()->create();
        $this->tenant = Tenant::factory()->create([
            'organization_id' => $org->id,
            'is_active' => true,
        ]);
        $this->user = User::factory()->for($this->tenant)->create();

        app(TenantManager::class)->setTenantContext($this->tenant->id);
        $this->seed(RolePermissionSeeder::class);
        app(PermissionRegistrar::class)->forgetCachedPermissions();
        $this->user->assignRole('owner');

        $this->clientVehicle = ClientVehicle::factory()->create(['tenant_id' => $this->tenant->id]);
    }

    protected function tearDown(): void
    {
        app(TenantManager::class)->clearTenantContext();
        parent::tearDown();
    }

    private function makeContact(?string $blockedUntil): Contact
    {
        return Contact::factory()->client()->create([
            'tenant_id' => $this->tenant->id,
            'blocked_until' => $blockedUntil,
        ]);
    }

    private function createWorkOrderFor(Contact $contact, array $extra = []): WorkOrder
    {
        return WorkOrder::create([
            'tenant_id' => $this->tenant->id,
            'client_vehicle_id' => $this->clientVehicle->id,
            'contact_id' => $contact->id,
            'code' => 'BLOCKED-'.uniqid(),
            'title' => 'Blocked contact test',
            'status' => 'draft',
            ...$extra,
        ]);
    }

    public function test_creating_work_order_for_blocked_contact_throws_validation_exception(): void
    {
        $contact = $this->makeContact(now()->addDays(3)->toIso8601String());

        try {
            $this->createWorkOrderFor($contact);
            $this->fail('Debió lanzar ValidationException para contacto bloqueado.');
        } catch (ValidationException $exception) {
            $this->assertArrayHasKey('contact_id', $exception->errors());
        }

        $this->assertDatabaseCount('work_orders', 0);
    }

    public function test_reception_action_rejects_blocked_contact(): void
    {
        $contact = $this->makeContact(now()->addDays(2)->toIso8601String());

        $action = app(CreateWorkOrderReceptionAction::class);

        try {
            $action->execute([
                'contact_id' => $contact->id,
                'client_vehicle_id' => $this->clientVehicle->id,
                'title' => 'Recepción bloqueada',
            ]);
            $this->fail('Debió lanzar ValidationException para contacto bloqueado.');
        } catch (ValidationException $exception) {
            $this->assertArrayHasKey('contact_id', $exception->errors());
        }

        $this->assertDatabaseCount('work_orders', 0);
    }

    public function test_wizard_surfaces_blocked_contact_error_with_form_error_and_notification(): void
    {
        $blockedUntil = now()->addDays(3);
        $contact = $this->makeContact($blockedUntil->toIso8601String());

        $this->actingAs($this->user);
        Filament::setCurrentPanel(app('filament')->getPanel('admin'));
        Filament::setTenant($this->tenant);

        $expectedMessage = sprintf(
            __('El contacto tiene un bloqueo activo hasta el %s. No se pueden crear órdenes para este contacto.'),
            $blockedUntil->format('Y-m-d H:i'),
        );

        Livewire::test(CreateWorkOrder::class)
            ->fillForm([
                'contact_id' => $contact->id,
                'client_vehicle_id' => $this->clientVehicle->id,
                'title' => 'Orden wizard bloqueada',
                'client_report' => 'El cliente reporta un ruido en el motor.',
                'status' => 'received',
                'priority' => 'medium',
            ])
            ->call('create')
            ->assertHasFormErrors(['contact_id'])
            ->assertNotified($expectedMessage);

        $this->assertDatabaseCount('work_orders', 0);
    }

    public function test_contact_without_block_allows_work_order_creation(): void
    {
        $contact = $this->makeContact(null);

        $workOrder = $this->createWorkOrderFor($contact);

        $this->assertDatabaseHas('work_orders', [
            'id' => $workOrder->id,
            'contact_id' => $contact->id,
        ]);
    }

    public function test_expired_block_allows_work_order_creation(): void
    {
        $contact = $this->makeContact(now()->subDay()->toIso8601String());

        $workOrder = $this->createWorkOrderFor($contact);

        $this->assertDatabaseHas('work_orders', [
            'id' => $workOrder->id,
            'contact_id' => $contact->id,
        ]);
    }

    public function test_editor_actor_is_blocked_by_date_not_role(): void
    {
        $editor = User::factory()->for($this->tenant)->create();
        $editor->assignRole('editor');
        $this->actingAs($editor);

        $contact = $this->makeContact(now()->addDays(5)->toIso8601String());

        $this->expectException(ValidationException::class);

        $this->createWorkOrderFor($contact);
    }

    public function test_editing_work_order_reassignment_to_blocked_contact_is_rejected(): void
    {
        $allowedContact = $this->makeContact(null);
        $blockedUntil = now()->addDays(3);
        $blockedContact = $this->makeContact($blockedUntil->toIso8601String());

        $workOrder = $this->createWorkOrderFor($allowedContact, [
            'priority' => 'medium',
            'client_report' => 'El cliente reporta un ruido en el motor.',
        ]);

        $this->actingAs($this->user);
        Filament::setCurrentPanel(app('filament')->getPanel('admin'));
        Filament::setTenant($this->tenant);

        $expectedMessage = sprintf(
            __('El contacto tiene un bloqueo activo hasta el %s. No se pueden crear órdenes para este contacto.'),
            $blockedUntil->format('Y-m-d H:i'),
        );

        Livewire::test(EditWorkOrder::class, ['record' => $workOrder->id])
            ->fillForm(['contact_id' => $blockedContact->id])
            ->call('save')
            ->assertHasFormErrors(['contact_id'])
            ->assertNotified($expectedMessage);

        $this->assertSame(
            $allowedContact->id,
            $workOrder->fresh()->contact_id,
            'La reasignación a un contacto bloqueado no debe persistirse.',
        );
    }
}
