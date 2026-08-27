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
        Schema::create('purchase_order_items', function (Blueprint $table) {
            $table->uuid('id')->primary()->default(DB::raw('gen_random_uuid()'));
            $table->foreignUuid('tenant_id')->constrained('tenants')->cascadeOnDelete();
            $table->foreignUuid('purchase_order_id')->constrained('purchase_orders')->cascadeOnDelete();
            $table->foreignUuid('item_id')->nullable()->constrained('items')->restrictOnDelete();
            $table->string('description', 255);
            $table->decimal('quantity', 10, 4);
            $table->decimal('received_quantity', 10, 4)->default(0);
            $table->decimal('unit_cost', 12, 2);
            $table->decimal('tax_rate', 5, 2)->default(19);
            $table->decimal('tax_amount', 12, 2)->default(0);
            $table->decimal('subtotal', 12, 2)->default(0);
            $table->string('batch_number', 50)->nullable();
            $table->date('expires_at')->nullable();
            $table->jsonb('metadata')->default('{}');
            $table->timestamps();
            $table->softDeletes();

            $table->index('tenant_id');
            $table->index(['tenant_id', 'purchase_order_id']);
            $table->index(['tenant_id', 'item_id']);
        });

        DB::statement('ALTER TABLE purchase_order_items ENABLE ROW LEVEL SECURITY');
        DB::statement('ALTER TABLE purchase_order_items FORCE ROW LEVEL SECURITY');

        DB::statement(<<<'SQL'
            CREATE POLICY purchase_order_items_tenant_isolation ON purchase_order_items
                FOR SELECT USING (tenant_id = public.current_tenant_id())
        SQL);
        DB::statement(<<<'SQL'
            CREATE POLICY purchase_order_items_insert ON purchase_order_items
                FOR INSERT WITH CHECK (tenant_id = public.current_tenant_id())
        SQL);
        DB::statement(<<<'SQL'
            CREATE POLICY purchase_order_items_update ON purchase_order_items
                FOR UPDATE USING (tenant_id = public.current_tenant_id())
        SQL);
        DB::statement(<<<'SQL'
            CREATE POLICY purchase_order_items_delete ON purchase_order_items
                FOR DELETE USING (tenant_id = public.current_tenant_id())
        SQL);
    }

    public function down(): void
    {
        DB::statement('DROP POLICY IF EXISTS purchase_order_items_delete ON purchase_order_items');
        DB::statement('DROP POLICY IF EXISTS purchase_order_items_update ON purchase_order_items');
        DB::statement('DROP POLICY IF EXISTS purchase_order_items_insert ON purchase_order_items');
        DB::statement('DROP POLICY IF EXISTS purchase_order_items_tenant_isolation ON purchase_order_items');
        DB::statement('ALTER TABLE purchase_order_items FORCE ROW LEVEL SECURITY');
        DB::statement('ALTER TABLE purchase_order_items DISABLE ROW LEVEL SECURITY');

        Schema::dropIfExists('purchase_order_items');
    }
};
