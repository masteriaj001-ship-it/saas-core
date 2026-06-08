<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('invoice_items', function (Blueprint $table): void {
            $table->uuid('id')->primary();
            $table->foreignUuid('tenant_id')->constrained()->cascadeOnDelete();
            $table->foreignUuid('invoice_id')->constrained()->cascadeOnDelete();
            $table->foreignUuid('work_order_item_id')->nullable()->constrained()->nullOnDelete();
            $table->text('description');
            $table->decimal('quantity', 10, 4)->default(1);
            $table->decimal('unit_price', 12, 2)->default(0);
            $table->decimal('discount', 12, 2)->default(0);
            $table->decimal('tax_rate', 5, 2)->default(19.00);
            $table->decimal('tax_amount', 12, 2)->default(0);
            $table->decimal('subtotal', 12, 2)->default(0);
            $table->decimal('total', 12, 2)->default(0);
            $table->timestamps();
        });

        DB::statement('ALTER TABLE invoice_items ENABLE ROW LEVEL SECURITY');
        DB::statement('ALTER TABLE invoice_items FORCE ROW LEVEL SECURITY');

        DB::statement('CREATE INDEX IF NOT EXISTS idx_invoice_items_invoice ON invoice_items (tenant_id, invoice_id)');
        DB::statement('CREATE INDEX IF NOT EXISTS idx_invoice_items_wo_item ON invoice_items (tenant_id, work_order_item_id) WHERE work_order_item_id IS NOT NULL');
    }

    public function down(): void
    {
        Schema::dropIfExists('invoice_items');
    }
};
