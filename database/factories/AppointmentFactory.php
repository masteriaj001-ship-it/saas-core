<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Models\Contact;
use App\Models\Location;
use App\Models\Tenant;
use App\Models\User;
use App\Modules\Talleres\Enums\AppointmentStatus;
use App\Modules\Talleres\Models\Appointment;
use App\Modules\Talleres\Models\ClientVehicle;
use App\Modules\Talleres\Models\WorkshopBay;
use Illuminate\Database\Eloquent\Factories\Factory;

class AppointmentFactory extends Factory
{
    protected $model = Appointment::class;

    public function definition(): array
    {
        return [
            'tenant_id' => Tenant::factory(),
            'contact_id' => Contact::factory(),
            'client_vehicle_id' => ClientVehicle::factory(),
            'location_id' => Location::factory(),
            'bay_id' => WorkshopBay::factory(),
            'mechanic_id' => User::factory(),
            'title' => fake()->randomElement(['Cambio de aceite', 'Revisión general', 'Reparación de frenos', 'Alineación', 'Cambio de llantas']),
            'description' => fake()->sentence(),
            'status' => AppointmentStatus::SCHEDULED,
            'scheduled_at' => fake()->dateTimeBetween('+1 day', '+1 month'),
            'duration_minutes' => fake()->randomElement([30, 60, 90, 120, 180]),
            'started_at' => null,
            'completed_at' => null,
            'notes' => fake()->optional()->sentence(),
            'metadata' => '{}',
        ];
    }

    public function scheduled(): static
    {
        return $this->state(fn (array $attrs) => [
            'status' => AppointmentStatus::SCHEDULED,
        ]);
    }

    public function confirmed(): static
    {
        return $this->state(fn (array $attrs) => [
            'status' => AppointmentStatus::CONFIRMED,
        ]);
    }

    public function inProgress(): static
    {
        return $this->state(fn (array $attrs) => [
            'status' => AppointmentStatus::IN_PROGRESS,
            'started_at' => now(),
        ]);
    }

    public function completed(): static
    {
        return $this->state(fn (array $attrs) => [
            'status' => AppointmentStatus::COMPLETED,
            'started_at' => now()->subHours(2),
            'completed_at' => now(),
        ]);
    }
}
