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
        Schema::create('stock_movements', function (Blueprint $table) {
            $table->uuid('id')->primary()->default(DB::raw('gen_random_uuid()'));
            $table->foreignUuid('tenant_id')->constrained('tenants')->cascadeOnDelete();
            $table->foreignUuid('item_id')->constrained('items')->restrictOnDelete();
            $table->foreignUuid('warehouse_id')->constrained('warehouses')->restrictOnDelete();
            $table->foreignUuid('user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->string('movement_type', 20);
            $table->integer('quantity');
            $table->integer('stock_before');
            $table->integer('stock_after');
            $table->decimal('unit_cost', 12, 2)->nullable();
            $table->string('reference_type', 50)->nullable();
            $table->uuid('reference_id')->nullable();
            $table->string('reason', 100);
            $table->text('notes')->nullable();
            $table->timestamps();

            $table->index('tenant_id');
            $table->index(['item_id', 'warehouse_id']);
            $table->index(['reference_type', 'reference_id']);
            $table->index('created_at');
        });

        DB::statement('ALTER TABLE stock_movements ENABLE ROW LEVEL SECURITY');
        DB::statement('ALTER TABLE stock_movements FORCE ROW LEVEL SECURITY');

        DB::statement(<<<'SQL'
            CREATE POLICY tenant_isolation_select ON stock_movements
                FOR SELECT USING (tenant_id = public.current_tenant_id())
        SQL);
        DB::statement(<<<'SQL'
            CREATE POLICY tenant_isolation_insert ON stock_movements
                FOR INSERT WITH CHECK (tenant_id = public.current_tenant_id())
        SQL);
        DB::statement(<<<'SQL'
            CREATE POLICY tenant_isolation_update ON stock_movements
                FOR UPDATE USING (tenant_id = public.current_tenant_id())
        SQL);
        DB::statement(<<<'SQL'
            CREATE POLICY tenant_isolation_delete ON stock_movements
                FOR DELETE USING (tenant_id = public.current_tenant_id())
        SQL);
    }

    public function down(): void
    {
        DB::statement('DROP POLICY IF EXISTS tenant_isolation_delete ON stock_movements');
        DB::statement('DROP POLICY IF EXISTS tenant_isolation_update ON stock_movements');
        DB::statement('DROP POLICY IF EXISTS tenant_isolation_insert ON stock_movements');
        DB::statement('DROP POLICY IF EXISTS tenant_isolation_select ON stock_movements');
        DB::statement('ALTER TABLE stock_movements FORCE ROW LEVEL SECURITY');
        DB::statement('ALTER TABLE stock_movements DISABLE ROW LEVEL SECURITY');

        Schema::dropIfExists('stock_movements');
    }
};
