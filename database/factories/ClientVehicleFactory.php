<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Models\Contact;
use App\Models\Tenant;
use App\Modules\Talleres\Models\ClientVehicle;
use Illuminate\Database\Eloquent\Factories\Factory;

class ClientVehicleFactory extends Factory
{
    protected $model = ClientVehicle::class;

    public function definition(): array
    {
        return [
            'tenant_id' => Tenant::factory(),
            'owner_contact_id' => Contact::factory(),
            'plate' => strtoupper(fake()->bothify('???-####')),
            'brand' => fake()->randomElement(['Toyota', 'Honda', 'Nissan', 'Ford', 'Chevrolet', 'Mazda']),
            'model' => fake()->word(),
            'year' => fake()->numberBetween(2000, (int) now()->format('Y')),
            'vin' => strtoupper(fake()->bothify('1HG?????????????')),
            'fuel_type' => fake()->randomElement(['gasoline', 'diesel', 'hybrid']),
            'current_mileage' => fake()->numberBetween(0, 200000),
            'color' => fake()->safeColorName(),
            'metadata' => '{}',
        ];
    }

    public function sedan(): static
    {
        return $this->state(fn (array $attrs) => [
            'vehicle_type' => 'sedan',
        ]);
    }

    public function suv(): static
    {
        return $this->state(fn (array $attrs) => [
            'vehicle_type' => 'suv',
        ]);
    }

    public function pickup(): static
    {
        return $this->state(fn (array $attrs) => [
            'vehicle_type' => 'pickup_truck',
        ]);
    }

    public function motorcycle(): static
    {
        return $this->state(fn (array $attrs) => [
            'vehicle_type' => 'motorcycle',
        ]);
    }
}
