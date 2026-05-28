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
        Schema::create('work_orders', function (Blueprint $table) {
            $table->uuid('id')->primary()->default(DB::raw('gen_random_uuid()'));
            $table->foreignUuid('tenant_id')->constrained('tenants')->cascadeOnDelete();
            $table->foreignUuid('asset_id')->constrained('assets')->cascadeOnDelete();
            $table->foreignUuid('contact_id')->nullable()->constrained('contacts')->nullOnDelete();
            $table->string('code', 50);
            $table->string('title', 255);
            $table->text('description')->nullable();
            $table->string('priority', 20)->default('normal');
            $table->string('status', 20)->default('draft');
            $table->timestamp('started_at')->nullable();
            $table->timestamp('completed_at')->nullable();
            $table->jsonb('metadata')->default('{}');
            $table->softDeletes();
            $table->timestamps();

            $table->index('tenant_id');
            $table->index(['tenant_id', 'status']);
            $table->unique(['tenant_id', 'code']);
            $table->index(['tenant_id', 'asset_id']);
        });

        DB::unprepared('
            ALTER TABLE work_orders ENABLE ROW LEVEL SECURITY;
            ALTER TABLE work_orders FORCE ROW LEVEL SECURITY;

            CREATE POLICY "work_orders_tenant_isolation_select"
                ON work_orders FOR SELECT
                USING (tenant_id = public.current_tenant_id());

            CREATE POLICY "work_orders_tenant_isolation_insert"
                ON work_orders FOR INSERT
                WITH CHECK (tenant_id = public.current_tenant_id());

            CREATE POLICY "work_orders_tenant_isolation_update"
                ON work_orders FOR UPDATE
                USING (tenant_id = public.current_tenant_id())
                WITH CHECK (tenant_id = public.current_tenant_id());

            CREATE POLICY "work_orders_tenant_isolation_delete"
                ON work_orders FOR DELETE
                USING (tenant_id = public.current_tenant_id());
        ');

        Schema::create('work_order_items', function (Blueprint $table) {
            $table->uuid('id')->primary()->default(DB::raw('gen_random_uuid()'));
            $table->foreignUuid('tenant_id')->constrained('tenants')->cascadeOnDelete();
            $table->foreignUuid('work_order_id')->constrained('work_orders')->cascadeOnDelete();
            $table->foreignUuid('item_id')->constrained('items')->cascadeOnDelete();
            $table->decimal('quantity', 12, 4)->default(1);
            $table->decimal('unit_price', 14, 4)->default(0);
            $table->text('description')->nullable();
            $table->jsonb('metadata')->default('{}');
            $table->softDeletes();
            $table->timestamps();

            $table->index('tenant_id');
            $table->index('work_order_id');
            $table->index(['tenant_id', 'item_id']);
        });

        DB::unprepared('
            ALTER TABLE work_order_items ENABLE ROW LEVEL SECURITY;
            ALTER TABLE work_order_items FORCE ROW LEVEL SECURITY;

            CREATE POLICY "work_order_items_tenant_isolation_select"
                ON work_order_items FOR SELECT
                USING (tenant_id = public.current_tenant_id());

            CREATE POLICY "work_order_items_tenant_isolation_insert"
                ON work_order_items FOR INSERT
                WITH CHECK (tenant_id = public.current_tenant_id());

            CREATE POLICY "work_order_items_tenant_isolation_update"
                ON work_order_items FOR UPDATE
                USING (tenant_id = public.current_tenant_id())
                WITH CHECK (tenant_id = public.current_tenant_id());

            CREATE POLICY "work_order_items_tenant_isolation_delete"
                ON work_order_items FOR DELETE
                USING (tenant_id = public.current_tenant_id());
        ');
    }

    public function down(): void
    {
        DB::unprepared('
            DROP POLICY IF EXISTS "work_order_items_tenant_isolation_delete" ON work_order_items;
            DROP POLICY IF EXISTS "work_order_items_tenant_isolation_update" ON work_order_items;
            DROP POLICY IF EXISTS "work_order_items_tenant_isolation_insert" ON work_order_items;
            DROP POLICY IF EXISTS "work_order_items_tenant_isolation_select" ON work_order_items;
            ALTER TABLE work_order_items FORCE ROW LEVEL SECURITY;
            ALTER TABLE work_order_items DISABLE ROW LEVEL SECURITY;
        ');
        Schema::dropIfExists('work_order_items');

        DB::unprepared('
            DROP POLICY IF EXISTS "work_orders_tenant_isolation_delete" ON work_orders;
            DROP POLICY IF EXISTS "work_orders_tenant_isolation_update" ON work_orders;
            DROP POLICY IF EXISTS "work_orders_tenant_isolation_insert" ON work_orders;
            DROP POLICY IF EXISTS "work_orders_tenant_isolation_select" ON work_orders;
            ALTER TABLE work_orders FORCE ROW LEVEL SECURITY;
            ALTER TABLE work_orders DISABLE ROW LEVEL SECURITY;
        ');
        Schema::dropIfExists('work_orders');
    }
};
