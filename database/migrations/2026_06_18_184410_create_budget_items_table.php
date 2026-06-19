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
        Schema::create('budget_items', function (Blueprint $table): void {
            $table->uuid('id')->primary()->default(DB::raw('gen_random_uuid()'));
            $table->uuid('tenant_id')->notNullable();
            $table->uuid('budget_id')->notNullable();
            $table->text('description')->notNullable();
            $table->decimal('quantity', 12, 4)->notNullable()->default(1);
            $table->decimal('unit_price', 14, 2)->notNullable()->default(0);
            $table->decimal('discount', 14, 2)->notNullable()->default(0);
            $table->decimal('tax_rate', 5, 2)->notNullable()->default(0);
            $table->decimal('subtotal', 14, 2)->notNullable()->default(0);
            $table->decimal('total', 14, 2)->notNullable()->default(0);
            $table->integer('sort_order')->notNullable()->default(0);
            $table->timestampsTz();

            $table->foreign('tenant_id')->references('id')->on('tenants')->onDelete('cascade');
            $table->foreign('budget_id')->references('id')->on('budgets')->onDelete('cascade');
        });

        DB::statement('ALTER TABLE budget_items ENABLE ROW LEVEL SECURITY');
        DB::statement('CREATE POLICY tenant_isolation_budget_items ON budget_items USING (tenant_id = current_tenant_id())');
    }

    public function down(): void
    {
        Schema::dropIfExists('budget_items');
    }
};
