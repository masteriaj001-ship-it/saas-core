<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Enums\ContactRoleEnum;
use App\Models\Contact;
use App\Models\ContactRole;
use App\Models\Tenant;
use Illuminate\Database\Eloquent\Factories\Factory;

class ContactRoleFactory extends Factory
{
    protected $model = ContactRole::class;

    public function definition(): array
    {
        return [
            'tenant_id' => Tenant::factory(),
            'contact_id' => Contact::factory(),
            'role_code' => fake()->randomElement(ContactRoleEnum::cases())->value,
            'metadata' => '{}',
        ];
    }

    public function mechanic(): static
    {
        return $this->state(fn (array $attrs) => [
            'role_code' => ContactRoleEnum::Mechanic->value,
        ]);
    }

    public function serviceAdvisor(): static
    {
        return $this->state(fn (array $attrs) => [
            'role_code' => ContactRoleEnum::ServiceAdvisor->value,
        ]);
    }

    public function workshopManager(): static
    {
        return $this->state(fn (array $attrs) => [
            'role_code' => ContactRoleEnum::WorkshopManager->value,
        ]);
    }

    public function technician(): static
    {
        return $this->state(fn (array $attrs) => [
            'role_code' => ContactRoleEnum::Technician->value,
        ]);
    }
}
