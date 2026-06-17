<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Modules\Talleres\Models\SmsCode;
use Illuminate\Database\Eloquent\Factories\Factory;

class SmsCodeFactory extends Factory
{
    protected $model = SmsCode::class;

    public function definition(): array
    {
        return [
            'code' => fake()->numerify('######'),
            'expires_at' => now()->addMinutes(15),
        ];
    }

    public function expired(): static
    {
        return $this->state(fn (array $attrs) => [
            'expires_at' => now()->subMinute(),
        ]);
    }

    public function used(): static
    {
        return $this->state(fn (array $attrs) => [
            'used_at' => now(),
        ]);
    }

    public function maxAttempts(): static
    {
        return $this->state(fn (array $attrs) => [
            'attempts' => 5,
        ]);
    }

    public function maxSends(): static
    {
        return $this->state(fn (array $attrs) => [
            'send_count' => 3,
        ]);
    }
}
