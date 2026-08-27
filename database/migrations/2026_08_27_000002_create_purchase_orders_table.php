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
        Schema::create('purchase_orders', function (Blueprint $table) {
            $table->uuid('id')->primary()->default(DB::raw('gen_random_uuid()'));
            $table->foreignUuid('tenant_id')->constrained('tenants')->cascadeOnDelete();
            $table->foreignUuid('supplier_id')->constrained('suppliers')->restrictOnDelete();
            $table->foreignUuid('warehouse_id')->constrained('warehouses')->restrictOnDelete();
            $table->string('code', 30);
            $table->string('status', 50)->default('draft');
            $table->timestamp('ordered_at')->nullable();
            $table->timestamp('expected_at')->nullable();
            $table->timestamp('received_at')->nullable();
            $table->decimal('subtotal', 12, 2)->default(0);
            $table->decimal('tax_total', 12, 2)->default(0);
            $table->decimal('total', 12, 2)->default(0);
            $table->text('notes')->nullable();
            $table->jsonb('metadata')->default('{}');
            $table->foreignUuid('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignUuid('updated_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
            $table->softDeletes();

            $table->unique(['tenant_id', 'code']);
            $table->index('tenant_id');
            $table->index(['tenant_id', 'supplier_id']);
            $table->index(['tenant_id', 'status']);
            $table->index(['tenant_id', 'ordered_at']);
        });

        DB::statement('ALTER TABLE purchase_orders ENABLE ROW LEVEL SECURITY');
        DB::statement('ALTER TABLE purchase_orders FORCE ROW LEVEL SECURITY');

        DB::statement(<<<'SQL'
            CREATE POLICY purchase_orders_tenant_isolation ON purchase_orders
                FOR SELECT USING (tenant_id = public.current_tenant_id())
        SQL);
        DB::statement(<<<'SQL'
            CREATE POLICY purchase_orders_insert ON purchase_orders
                FOR INSERT WITH CHECK (tenant_id = public.current_tenant_id())
        SQL);
        DB::statement(<<<'SQL'
            CREATE POLICY purchase_orders_update ON purchase_orders
                FOR UPDATE USING (tenant_id = public.current_tenant_id())
        SQL);
        DB::statement(<<<'SQL'
            CREATE POLICY purchase_orders_delete ON purchase_orders
                FOR DELETE USING (tenant_id = public.current_tenant_id())
        SQL);
    }

    public function down(): void
    {
        DB::statement('DROP POLICY IF EXISTS purchase_orders_delete ON purchase_orders');
        DB::statement('DROP POLICY IF EXISTS purchase_orders_update ON purchase_orders');
        DB::statement('DROP POLICY IF EXISTS purchase_orders_insert ON purchase_orders');
        DB::statement('DROP POLICY IF EXISTS purchase_orders_tenant_isolation ON purchase_orders');
        DB::statement('ALTER TABLE purchase_orders FORCE ROW LEVEL SECURITY');
        DB::statement('ALTER TABLE purchase_orders DISABLE ROW LEVEL SECURITY');

        Schema::dropIfExists('purchase_orders');
    }
};
