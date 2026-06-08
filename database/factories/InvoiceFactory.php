<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Enums\InvoiceDocumentTypeEnum;
use App\Enums\InvoiceStatusEnum;
use App\Models\Contact;
use App\Models\Tenant;
use App\Modules\Facturacion\Models\Invoice;
use Illuminate\Database\Eloquent\Factories\Factory;

class InvoiceFactory extends Factory
{
    protected $model = Invoice::class;

    public function definition(): array
    {
        $prefix = 'FV';
        $sequence = fake()->unique()->numberBetween(1, 999999);

        return [
            'tenant_id' => Tenant::factory(),
            'contact_id' => Contact::factory()->client(),
            'work_order_id' => null,
            'document_type' => InvoiceDocumentTypeEnum::Invoice->value,
            'prefix' => $prefix,
            'sequence' => $sequence,
            'document_number' => "{$prefix}-{$sequence}",
            'status' => InvoiceStatusEnum::Draft->value,
            'issued_at' => null,
            'due_at' => null,
            'subtotal' => 0,
            'discount_total' => 0,
            'tax_total' => 0,
            'grand_total' => 0,
            'notes' => null,
        ];
    }

    public function issued(): static
    {
        return $this->state(fn (array $attrs) => [
            'status' => InvoiceStatusEnum::Issued->value,
            'issued_at' => now(),
            'due_at' => now()->addDays(30),
        ]);
    }

    public function paid(): static
    {
        return $this->state(fn (array $attrs) => [
            'status' => InvoiceStatusEnum::Paid->value,
            'issued_at' => now()->subDays(10),
            'due_at' => now()->subDays(5),
        ]);
    }

    public function cancelled(): static
    {
        return $this->state(fn (array $attrs) => [
            'status' => InvoiceStatusEnum::Cancelled->value,
        ]);
    }
}
