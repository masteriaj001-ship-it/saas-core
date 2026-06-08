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
        Schema::create('work_order_inspections', function (Blueprint $table): void {
            $table->uuid('id')->primary()->default(DB::raw('gen_random_uuid()'));

            $table->foreignUuid('tenant_id')
                ->constrained('tenants')
                ->cascadeOnDelete();

            $table->foreignUuid('work_order_id')
                ->constrained('work_orders')
                ->cascadeOnDelete();

            $table->string('item_name', 100);
            $table->string('status', 20);
            $table->text('notes')->nullable();
            $table->string('photo_path', 500)->nullable();
            $table->smallInteger('sort_order')->default(0);

            $table->timestampTz('created_at')->default(DB::raw('CURRENT_TIMESTAMP'));
            $table->timestampTz('updated_at')->default(DB::raw('CURRENT_TIMESTAMP'));

            $table->index('tenant_id', 'idx_woi_tenant');
            $table->index(['tenant_id', 'work_order_id'], 'idx_woi_work_order');
        });

        DB::statement('ALTER TABLE work_order_inspections ENABLE ROW LEVEL SECURITY');
        DB::statement('ALTER TABLE work_order_inspections FORCE ROW LEVEL SECURITY');

        DB::statement(<<<'SQL'
            CREATE POLICY tenant_isolation_select ON work_order_inspections
                FOR SELECT USING (tenant_id = current_tenant_id())
        SQL);

        DB::statement(<<<'SQL'
            CREATE POLICY tenant_isolation_insert ON work_order_inspections
                FOR INSERT WITH CHECK (tenant_id = current_tenant_id())
        SQL);

        DB::statement(<<<'SQL'
            CREATE POLICY tenant_isolation_update ON work_order_inspections
                FOR UPDATE USING (tenant_id = current_tenant_id())
        SQL);

        DB::statement(<<<'SQL'
            CREATE POLICY tenant_isolation_delete ON work_order_inspections
                FOR DELETE USING (tenant_id = current_tenant_id())
        SQL);
    }

    public function down(): void
    {
        Schema::dropIfExists('work_order_inspections');
    }
};
