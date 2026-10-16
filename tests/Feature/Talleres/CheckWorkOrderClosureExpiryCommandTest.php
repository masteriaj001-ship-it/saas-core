<?php

declare(strict_types=1);

namespace Tests\Feature\Talleres;

use App\Enums\WorkOrderStatusEnum;
use App\Models\Contact;
use App\Models\Organization;
use App\Models\Tenant;
use App\Models\User;
use App\Modules\Talleres\Console\Commands\CheckWorkOrderClosureExpiry;
use App\Modules\Talleres\Models\ClientVehicle;
use App\Modules\Talleres\Models\WorkOrder;
use App\Modules\Talleres\Models\WorkOrderActivity;
use App\Modules\Talleres\Notifications\WorkOrderBreachNotification;
use App\Modules\Talleres\Notifications\WorkOrderPickupAlertNotification;
use App\Modules\Talleres\Services\WorkOrderClosureService;
use App\Services\TenantManager;
use Database\Seeders\RolePermissionSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Facades\Schedule;
use RuntimeException;
use Spatie\Permission\PermissionRegistrar;
use Symfony\Component\Console\Input\ArrayInput;
use Symfony\Component\Console\Output\NullOutput;
use Tests\TestCase;

final class CheckWorkOrderClosureExpiryCommandTest extends TestCase
{
    use RefreshDatabase;

    private Tenant $tenant;

    private User $user;

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
    }

    protected function tearDown(): void
    {
        app(TenantManager::class)->clearTenantContext();
        parent::tearDown();
    }

    private function makeWorkOrder(
        WorkOrderStatusEnum $status,
        array $settings = [],
        ?Contact $contact = null,
        ?\DateTimeInterface $createdAt = null,
    ): WorkOrder {
        $contact ??= Contact::factory()->client()->create(['tenant_id' => $this->tenant->id]);
        $clientVehicle = ClientVehicle::factory()->create(['tenant_id' => $this->tenant->id]);

        $workOrder = new WorkOrder;
        $workOrder->forceFill([
            'tenant_id' => $this->tenant->id,
            'client_vehicle_id' => $clientVehicle->id,
            'contact_id' => $contact->id,
            'code' => 'CLOSURE-SWEEP-'.uniqid(),
            'title' => 'Closure sweep test',
            'status' => $status,
            'settings' => $settings,
            'created_at' => $createdAt ?? now(),
            'updated_at' => $createdAt ?? now(),
        ]);
        $workOrder->save();

        return $workOrder;
    }

    public function test_waiting_client_expired_transitions_to_breach_blocks_contact_and_notifies(): void
    {
        $contact = Contact::factory()->client()->create(['tenant_id' => $this->tenant->id]);
        $workOrder = $this->makeWorkOrder(
            WorkOrderStatusEnum::WaitingClient,
            ['closure_window_started_at' => now()->subHours(73)->toIso8601String()],
            $contact,
        );

        Notification::fake();

        $this->artisan('work-orders:check-closure-expiry')
            ->assertExitCode(0);

        $this->assertEquals(WorkOrderStatusEnum::Breach, $workOrder->fresh()->status);

        $blockedUntil = $contact->fresh()->blocked_until;
        $this->assertNotNull($blockedUntil);
        $this->assertEqualsWithDelta(now()->addDays(7)->timestamp, $blockedUntil->timestamp, 10);

        Notification::assertSentTo(
            [$this->user],
            WorkOrderBreachNotification::class,
        );
    }

    public function test_no_pickup_expired_transitions_to_breach_blocks_contact_and_notifies(): void
    {
        $contact = Contact::factory()->client()->create(['tenant_id' => $this->tenant->id]);
        $workOrder = $this->makeWorkOrder(
            WorkOrderStatusEnum::NoPickup,
            ['closure_window_started_at' => now()->subHours(80)->toIso8601String()],
            $contact,
        );

        Notification::fake();

        $this->artisan('work-orders:check-closure-expiry')
            ->assertExitCode(0);

        $this->assertEquals(WorkOrderStatusEnum::Breach, $workOrder->fresh()->status);

        $blockedUntil = $contact->fresh()->blocked_until;
        $this->assertNotNull($blockedUntil);
        $this->assertEqualsWithDelta(now()->addDays(7)->timestamp, $blockedUntil->timestamp, 10);

        Notification::assertSentTo(
            [$this->user],
            WorkOrderBreachNotification::class,
        );
    }

    public function test_breach_notification_failure_rolls_back_and_order_stays_eligible(): void
    {
        $contact = Contact::factory()->client()->create(['tenant_id' => $this->tenant->id]);
        $workOrder = $this->makeWorkOrder(
            WorkOrderStatusEnum::WaitingClient,
            ['closure_window_started_at' => now()->subHours(73)->toIso8601String()],
            $contact,
        );

        Notification::shouldReceive('send')
            ->andThrow(new RuntimeException('Fallo de base de datos simulado al notificar.'));

        $this->artisan('work-orders:check-closure-expiry')
            ->assertExitCode(0);

        $fresh = $workOrder->fresh();
        $this->assertEquals(
            WorkOrderStatusEnum::WaitingClient,
            $fresh->status,
            'Si la notificación de breach falla, la transacción completa debe revertirse y la orden seguir en el sweep.',
        );
        $this->assertNull($contact->fresh()->blocked_until);
        $this->assertEqualsWithDelta(
            now()->subHours(73)->timestamp,
            Carbon::parse($fresh->settings['closure_window_started_at'])->timestamp,
            10,
        );

        Notification::fake();

        $this->artisan('work-orders:check-closure-expiry')
            ->assertExitCode(0);

        $this->assertEquals(
            WorkOrderStatusEnum::Breach,
            $workOrder->fresh()->status,
            'El próximo run sin fallos debe poder procesar la breach pendiente (at-least-once).',
        );
        Notification::assertSentTo([$this->user], WorkOrderBreachNotification::class);
    }

    public function test_legacy_orders_are_skipped_without_killing_tenant_loop(): void
    {
        $legacy = $this->makeWorkOrder(
            WorkOrderStatusEnum::WaitingClient,
            [
                'is_legacy_closure' => true,
                'closure_window_started_at' => now()->subHours(73)->toIso8601String(),
            ],
        );
        $valid = $this->makeWorkOrder(
            WorkOrderStatusEnum::WaitingClient,
            ['closure_window_started_at' => now()->subHours(73)->toIso8601String()],
        );

        Notification::fake();

        $this->artisan('work-orders:check-closure-expiry')
            ->assertExitCode(0);
        $this->artisan('work-orders:check-closure-expiry')
            ->assertExitCode(0);

        $this->assertEquals(WorkOrderStatusEnum::WaitingClient, $legacy->fresh()->status);
        $this->assertEquals(WorkOrderStatusEnum::Breach, $valid->fresh()->status);

        $this->assertDatabaseMissing('work_order_activities', [
            'work_order_id' => $legacy->id,
            'type' => 'note',
        ]);
    }

    public function test_poison_window_stamp_is_isolated_and_tenant_context_is_cleared(): void
    {
        $poisonMalformed = $this->makeWorkOrder(
            WorkOrderStatusEnum::WaitingClient,
            ['closure_window_started_at' => 'no-es-una-fecha'],
            createdAt: now()->subMinutes(2),
        );

        $poisonNonScalar = $this->makeWorkOrder(
            WorkOrderStatusEnum::WaitingClient,
            ['closure_window_started_at' => ['nested' => 'stamp']],
            createdAt: now()->subMinute(),
        );

        $healthy = $this->makeWorkOrder(
            WorkOrderStatusEnum::WaitingClient,
            ['closure_window_started_at' => now()->subHours(73)->toIso8601String()],
            createdAt: now(),
        );

        Notification::fake();

        $this->artisan('work-orders:check-closure-expiry')
            ->assertExitCode(0);

        $this->assertEquals(WorkOrderStatusEnum::WaitingClient, $poisonMalformed->fresh()->status);
        $this->assertEquals(WorkOrderStatusEnum::WaitingClient, $poisonNonScalar->fresh()->status);
        $this->assertEquals(
            WorkOrderStatusEnum::Breach,
            $healthy->fresh()->status,
            'La orden sana del mismo tenant debe procesarse aunque otras órdenes fallen.',
        );

        $this->assertNull(
            app(TenantManager::class)->getCurrentTenantId(),
            'El contexto de tenant no debe quedar residual tras el sweep.',
        );

        $sessionTenant = DB::selectOne("SELECT current_setting('app.current_tenant_id', true) AS tenant");
        $this->assertEmpty($sessionTenant?->tenant, 'app.current_tenant_id debe quedar limpio en la sesión.');
    }

    public function test_fallback_uses_activity_row_when_settings_missing(): void
    {
        $workOrder = $this->makeWorkOrder(WorkOrderStatusEnum::WaitingClient);

        WorkOrderActivity::factory()->create([
            'work_order_id' => $workOrder->id,
            'tenant_id' => $this->tenant->id,
            'type' => 'status_change',
            'to_status' => WorkOrderStatusEnum::WaitingClient->value,
            'created_at' => now()->subHours(73),
            'updated_at' => now()->subHours(73),
        ]);

        Notification::fake();

        $this->artisan('work-orders:check-closure-expiry')
            ->assertExitCode(0);

        $this->assertEquals(WorkOrderStatusEnum::Breach, $workOrder->fresh()->status);
    }

    public function test_fallback_uses_created_at_when_no_settings_and_no_activity(): void
    {
        $workOrder = $this->makeWorkOrder(
            WorkOrderStatusEnum::NoPickup,
            createdAt: now()->subHours(73),
        );

        Notification::fake();
        Log::spy();

        $this->artisan('work-orders:check-closure-expiry')
            ->assertExitCode(0);

        Log::shouldHaveReceived('info', function (string $message, array $context = []): bool {
            return str_contains($message, 'created_at de la orden')
                && ($context['fallback'] ?? null) === 'created_at';
        });

        $this->assertEquals(WorkOrderStatusEnum::Breach, $workOrder->fresh()->status);
    }

    public function test_fresh_order_without_stamp_falls_back_to_created_at_within_window(): void
    {
        $workOrder = $this->makeWorkOrder(WorkOrderStatusEnum::WaitingClient);

        Notification::fake();

        $this->artisan('work-orders:check-closure-expiry')
            ->assertExitCode(0);

        $this->assertEquals(WorkOrderStatusEnum::WaitingClient, $workOrder->fresh()->status);
        Notification::assertNothingSent();
    }

    public function test_order_inside_window_is_not_breached(): void
    {
        $workOrder = $this->makeWorkOrder(
            WorkOrderStatusEnum::WaitingClient,
            ['closure_window_started_at' => now()->subHours(10)->toIso8601String()],
        );

        Notification::fake();

        $this->artisan('work-orders:check-closure-expiry')
            ->assertExitCode(0);

        $this->assertEquals(WorkOrderStatusEnum::WaitingClient, $workOrder->fresh()->status);
        Notification::assertNothingSent();
    }

    public function test_tenant_flag_limits_sweep(): void
    {
        $otherOrg = Organization::factory()->create();
        $otherTenant = Tenant::factory()->create([
            'organization_id' => $otherOrg->id,
            'is_active' => true,
        ]);
        $otherUser = User::factory()->for($otherTenant)->create();

        app(TenantManager::class)->setTenantContext($otherTenant->id);
        $this->seed(RolePermissionSeeder::class);
        $otherUser->assignRole('owner');
        app(TenantManager::class)->setTenantContext($this->tenant->id);

        $orderA = $this->makeWorkOrder(
            WorkOrderStatusEnum::WaitingClient,
            ['closure_window_started_at' => now()->subHours(73)->toIso8601String()],
        );

        $otherContact = Contact::factory()->client()->create(['tenant_id' => $otherTenant->id]);
        $otherVehicle = ClientVehicle::factory()->create(['tenant_id' => $otherTenant->id]);
        $orderB = new WorkOrder;
        $orderB->forceFill([
            'tenant_id' => $otherTenant->id,
            'client_vehicle_id' => $otherVehicle->id,
            'contact_id' => $otherContact->id,
            'code' => 'CLOSURE-SWEEP-B-'.uniqid(),
            'title' => 'Other tenant order',
            'status' => WorkOrderStatusEnum::WaitingClient,
            'settings' => ['closure_window_started_at' => now()->subHours(73)->toIso8601String()],
        ]);
        $orderB->save();

        Notification::fake();

        $this->artisan('work-orders:check-closure-expiry', ['--tenant' => $this->tenant->id])
            ->assertExitCode(0);

        $this->assertEquals(WorkOrderStatusEnum::Breach, $orderA->fresh()->status);
        $this->assertEquals(WorkOrderStatusEnum::WaitingClient, $orderB->fresh()->status);

        Notification::assertSentTo([$this->user], WorkOrderBreachNotification::class);
        Notification::assertNotSentTo([$otherUser], WorkOrderBreachNotification::class);
    }

    public function test_schedule_registered_hourly(): void
    {
        $schedule = Schedule::getFacadeRoot();

        $events = collect($schedule->events())
            ->filter(fn ($event): bool => str_contains($event->command ?? '', 'work-orders:check-closure-expiry'));

        $this->assertCount(1, $events, 'work-orders:check-closure-expiry debe estar registrado una sola vez en el schedule.');
        $this->assertSame('0 * * * *', $events->first()->expression, 'El comando debe correr con Schedule::hourly().');
        $this->assertTrue($events->first()->withoutOverlapping, 'El sweep debe registrar withoutOverlapping().');
    }

    public function test_handle_returns_failure_when_tenant_processing_fails(): void
    {
        $command = new class extends CheckWorkOrderClosureExpiry
        {
            protected function processTenant(string $tenantId): void
            {
                throw new RuntimeException('Fallo de proceso del tenant simulado.');
            }
        };

        $command->setLaravel($this->app);

        $exitCode = $command->run(new ArrayInput([]), new NullOutput);

        $this->assertSame(
            CheckWorkOrderClosureExpiry::FAILURE,
            $exitCode,
            'Un fallo de tenant debe devolver FAILURE para que los healthchecks/schedulers lo detecten.',
        );
    }

    public function test_pickup_alert_sent_for_no_pickup_between_48h_and_72h(): void
    {
        $contact = Contact::factory()->client()->create(['tenant_id' => $this->tenant->id]);
        $workOrder = $this->makeWorkOrder(
            WorkOrderStatusEnum::NoPickup,
            ['closure_window_started_at' => now()->subHours(49)->toIso8601String()],
            $contact,
        );

        Notification::fake();

        $this->artisan('work-orders:check-closure-expiry')
            ->assertExitCode(0);

        Notification::assertSentTo($this->user, WorkOrderPickupAlertNotification::class);

        $fresh = $workOrder->fresh();
        $this->assertEquals(WorkOrderStatusEnum::NoPickup, $fresh->status);
        $this->assertNotEmpty($fresh->settings['pickup_alert_sent_at'] ?? null);
        $this->assertNull($contact->fresh()->blocked_until);
    }

    public function test_pickup_alert_is_idempotent_across_runs(): void
    {
        $contact = Contact::factory()->client()->create(['tenant_id' => $this->tenant->id]);
        $workOrder = $this->makeWorkOrder(
            WorkOrderStatusEnum::NoPickup,
            ['closure_window_started_at' => now()->subHours(49)->toIso8601String()],
            $contact,
        );

        Notification::fake();

        $this->artisan('work-orders:check-closure-expiry')->assertExitCode(0);
        $this->artisan('work-orders:check-closure-expiry')->assertExitCode(0);

        Notification::assertSentToTimes($this->user, WorkOrderPickupAlertNotification::class, 1);
        $this->assertEquals(WorkOrderStatusEnum::NoPickup, $workOrder->fresh()->status);
    }

    public function test_pickup_alert_not_sent_for_waiting_client(): void
    {
        $waitingContact = Contact::factory()->client()->create(['tenant_id' => $this->tenant->id]);
        $waitingOrder = $this->makeWorkOrder(
            WorkOrderStatusEnum::WaitingClient,
            ['closure_window_started_at' => now()->subHours(49)->toIso8601String()],
            $waitingContact,
        );

        $controlContact = Contact::factory()->client()->create(['tenant_id' => $this->tenant->id]);
        $controlOrder = $this->makeWorkOrder(
            WorkOrderStatusEnum::NoPickup,
            ['closure_window_started_at' => now()->subHours(49)->toIso8601String()],
            $controlContact,
        );

        Notification::fake();

        $this->artisan('work-orders:check-closure-expiry')
            ->assertExitCode(0);

        Notification::assertSentTo(
            $this->user,
            WorkOrderPickupAlertNotification::class,
            fn (WorkOrderPickupAlertNotification $notification): bool => str_contains($notification->workOrderCode, $controlOrder->code)
        );

        Notification::assertNotSentTo(
            $this->user,
            WorkOrderPickupAlertNotification::class,
            fn (WorkOrderPickupAlertNotification $notification): bool => str_contains($notification->workOrderCode, $waitingOrder->code)
        );

        $fresh = $waitingOrder->fresh();
        $this->assertEquals(WorkOrderStatusEnum::WaitingClient, $fresh->status);
        $this->assertArrayNotHasKey('pickup_alert_sent_at', $fresh->settings ?? []);
    }

    public function test_pickup_alert_realerts_after_reentry_to_no_pickup(): void
    {
        $contact = Contact::factory()->client()->create(['tenant_id' => $this->tenant->id]);
        $workOrder = $this->makeWorkOrder(
            WorkOrderStatusEnum::NoPickup,
            [
                'closure_window_started_at' => now()->subHours(49)->toIso8601String(),
                'pickup_alert_sent_at' => now()->subHour()->toIso8601String(),
            ],
            $contact,
        );

        Notification::fake();

        $this->artisan('work-orders:check-closure-expiry')->assertExitCode(0);
        Notification::assertNotSentTo($this->user, WorkOrderPickupAlertNotification::class);

        app(TenantManager::class)->setTenantContext($this->tenant->id);

        $workOrder->forceFill([
            'status' => WorkOrderStatusEnum::WaitingClient,
            'signature_hash' => 'signature-hash',
            'signed_at' => now(),
        ])->save();

        $service = app(WorkOrderClosureService::class);
        $reentered = $service->transition($workOrder->fresh(), WorkOrderStatusEnum::NoPickup);

        $this->assertArrayNotHasKey(
            'pickup_alert_sent_at',
            $reentered->settings ?? [],
            'Reingresar a no_pickup debe limpiar pickup_alert_sent_at para volver a alertar.'
        );

        $reentered->forceFill([
            'settings' => array_merge($reentered->settings ?? [], [
                'closure_window_started_at' => now()->subHours(49)->toIso8601String(),
            ]),
        ])->save();

        app(TenantManager::class)->clearTenantContext();

        $this->artisan('work-orders:check-closure-expiry')->assertExitCode(0);

        Notification::assertSentToTimes($this->user, WorkOrderPickupAlertNotification::class, 1);
    }
}
