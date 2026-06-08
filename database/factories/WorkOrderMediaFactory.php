<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Modules\Talleres\Models\WorkOrder;
use App\Modules\Talleres\Models\WorkOrderInspection;
use App\Modules\Talleres\Models\WorkOrderMedia;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

class WorkOrderMediaFactory extends Factory
{
    protected $model = WorkOrderMedia::class;

    public function definition(): array
    {
        return [
            'work_order_id' => WorkOrder::factory(),
            'work_order_inspection_id' => null,
            'user_id' => null,
            'original_name' => $this->faker->word().'.jpg',
            'storage_path' => (string) Str::uuid(),
            'mime_type' => 'image/jpeg',
            'size' => $this->faker->numberBetween(1000, 50000),
            'metadata' => [
                'category' => null,
                'source' => null,
                'uploaded_via' => null,
            ],
        ];
    }

    public function asPdf(): self
    {
        return $this->state(fn (array $attributes): array => [
            'original_name' => $this->faker->word().'.pdf',
            'mime_type' => 'application/pdf',
            'size' => $this->faker->numberBetween(5000, 200000),
        ]);
    }

    public function asVideo(): self
    {
        return $this->state(fn (array $attributes): array => [
            'original_name' => $this->faker->word().'.mp4',
            'mime_type' => 'video/mp4',
            'size' => $this->faker->numberBetween(500000, 5000000),
        ]);
    }

    public function forInspection(WorkOrderInspection $inspection): self
    {
        return $this->state(fn (array $attributes): array => [
            'work_order_inspection_id' => $inspection->id,
        ]);
    }
}
