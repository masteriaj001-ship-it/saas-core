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
        Schema::create('items', function (Blueprint $table) {
            $table->uuid('id')->primary()->default(DB::raw('gen_random_uuid()'));
            $table->foreignUuid('tenant_id')->constrained('tenants')->cascadeOnDelete();
            $table->string('sku', 100);
            $table->string('name', 255);
            $table->text('description')->nullable();
            $table->string('item_type', 50)->default('product');
            $table->string('unit', 30)->default('unit');
            $table->decimal('price', 12, 2)->default(0);
            $table->decimal('cost', 12, 2)->default(0);
            $table->integer('stock')->default(0);
            $table->integer('min_stock')->default(0);
            $table->jsonb('metadata')->default('{}');
            $table->softDeletes();
            $table->timestamps();

            $table->index('tenant_id');
            $table->unique(['tenant_id', 'sku']);
            $table->index(['tenant_id', 'item_type']);
        });

        DB::unprepared('
            ALTER TABLE items ENABLE ROW LEVEL SECURITY;
            ALTER TABLE items FORCE ROW LEVEL SECURITY;

            CREATE POLICY "items_tenant_isolation_select"
                ON items FOR SELECT
                USING (tenant_id = public.current_tenant_id());

            CREATE POLICY "items_tenant_isolation_insert"
                ON items FOR INSERT
                WITH CHECK (tenant_id = public.current_tenant_id());

            CREATE POLICY "items_tenant_isolation_update"
                ON items FOR UPDATE
                USING (tenant_id = public.current_tenant_id())
                WITH CHECK (tenant_id = public.current_tenant_id());

            CREATE POLICY "items_tenant_isolation_delete"
                ON items FOR DELETE
                USING (tenant_id = public.current_tenant_id());
        ');
    }

    public function down(): void
    {
        DB::unprepared('
            DROP POLICY IF EXISTS "items_tenant_isolation_delete" ON items;
            DROP POLICY IF EXISTS "items_tenant_isolation_update" ON items;
            DROP POLICY IF EXISTS "items_tenant_isolation_insert" ON items;
            DROP POLICY IF EXISTS "items_tenant_isolation_select" ON items;
            ALTER TABLE items FORCE ROW LEVEL SECURITY;
            ALTER TABLE items DISABLE ROW LEVEL SECURITY;
        ');

        Schema::dropIfExists('items');
    }
};
