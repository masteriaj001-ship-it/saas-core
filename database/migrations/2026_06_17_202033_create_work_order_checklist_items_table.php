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
        Schema::create('work_order_checklist_items', function (Blueprint $table): void {
            $table->uuid('id')->primary()->default(DB::raw('gen_random_uuid()'));

            $table->foreignUuid('tenant_id')
                ->constrained('tenants')
                ->cascadeOnDelete();

            $table->foreignUuid('work_order_id')
                ->constrained('work_orders')
                ->cascadeOnDelete();

            $table->string('task', 255);
            $table->string('status', 20)->default('pending');
            $table->smallInteger('position')->default(0);
            $table->text('notes')->nullable();

            $table->foreignUuid('assigned_to')
                ->nullable()
                ->constrained('contacts')
                ->nullOnDelete();

            $table->foreignUuid('completed_by')
                ->nullable()
                ->constrained('contacts')
                ->nullOnDelete();

            $table->timestampTz('completed_at')->nullable();
            $table->timestampTz('created_at')->default(DB::raw('CURRENT_TIMESTAMP'));
            $table->timestampTz('updated_at')->default(DB::raw('CURRENT_TIMESTAMP'));
            $table->softDeletesTz();
        });

        DB::statement('ALTER TABLE work_order_checklist_items ENABLE ROW LEVEL SECURITY');
        DB::statement('ALTER TABLE work_order_checklist_items FORCE ROW LEVEL SECURITY');

        DB::statement(<<<'SQL'
            CREATE POLICY tenant_isolation_select ON work_order_checklist_items
                FOR SELECT USING (tenant_id = public.current_tenant_id())
        SQL);

        DB::statement(<<<'SQL'
            CREATE POLICY tenant_isolation_insert ON work_order_checklist_items
                FOR INSERT WITH CHECK (tenant_id = public.current_tenant_id())
        SQL);

        DB::statement(<<<'SQL'
            CREATE POLICY tenant_isolation_update ON work_order_checklist_items
                FOR UPDATE USING (tenant_id = public.current_tenant_id())
        SQL);

        DB::statement(<<<'SQL'
            CREATE POLICY tenant_isolation_delete ON work_order_checklist_items
                FOR DELETE USING (tenant_id = public.current_tenant_id())
        SQL);
    }

    public function down(): void
    {
        Schema::dropIfExists('work_order_checklist_items');
    }
};
