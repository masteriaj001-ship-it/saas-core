<?php

declare(strict_types=1);

namespace Tests\Feature\Talleres;

use App\Models\Contact;
use App\Models\Tenant;
use App\Models\User;
use App\Modules\Talleres\Enums\AppointmentStatus;
use App\Modules\Talleres\Models\Appointment;
use App\Modules\Talleres\Models\WorkshopBay;
use App\Services\TenantManager;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

final class AppointmentTest extends TestCase
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

    public function test_appointment_can_be_created(): void
    {
        $appointment = Appointment::factory()->create([
            'tenant_id' => $this->tenant->id,
        ]);

        $this->assertDatabaseHas('appointments', [
            'id' => $appointment->id,
            'tenant_id' => $this->tenant->id,
        ]);
    }

    public function test_appointment_has_relationships(): void
    {
        $contact = Contact::factory()->create([
            'tenant_id' => $this->tenant->id,
        ]);
        $bay = WorkshopBay::factory()->create([
            'tenant_id' => $this->tenant->id,
        ]);
        $mechanic = User::factory()->for($this->tenant)->create();

        $appointment = Appointment::factory()->create([
            'tenant_id' => $this->tenant->id,
            'contact_id' => $contact->id,
            'bay_id' => $bay->id,
            'mechanic_id' => $mechanic->id,
        ]);

        $this->assertNotNull($appointment->contact);
        $this->assertNotNull($appointment->bay);
        $this->assertNotNull($appointment->mechanic);
    }

    public function test_appointment_status_can_be_changed(): void
    {
        $appointment = Appointment::factory()->scheduled()->create([
            'tenant_id' => $this->tenant->id,
        ]);

        $appointment->update(['status' => AppointmentStatus::CONFIRMED]);

        $this->assertEquals(AppointmentStatus::CONFIRMED, $appointment->fresh()->status);
    }

    public function test_appointment_scheduled_end(): void
    {
        $appointment = Appointment::factory()->create([
            'tenant_id' => $this->tenant->id,
            'scheduled_at' => now()->addDay()->setTime(10, 0),
            'duration_minutes' => 120,
        ]);

        $expectedEnd = $appointment->scheduled_at->clone()->addMinutes(120);

        $this->assertEquals($expectedEnd, $appointment->scheduledEnd());
    }
}
