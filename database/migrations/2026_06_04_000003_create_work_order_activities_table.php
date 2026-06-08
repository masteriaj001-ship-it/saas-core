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
        Schema::create('work_order_activities', function (Blueprint $table): void {
            $table->uuid('id')->primary()->default(DB::raw('gen_random_uuid()'));

            $table->foreignUuid('tenant_id')
                ->constrained('tenants')
                ->cascadeOnDelete();

            $table->foreignUuid('work_order_id')
                ->constrained('work_orders')
                ->cascadeOnDelete();

            $table->foreignUuid('user_id')
                ->nullable()
                ->constrained('users')
                ->nullOnDelete();

            $table->string('type', 50);
            $table->text('description');
            $table->string('from_status', 50)->nullable();
            $table->string('to_status', 50)->nullable();
            $table->jsonb('metadata')->default('{}');

            $table->timestampTz('created_at')->default(DB::raw('CURRENT_TIMESTAMP'));
            $table->timestampTz('updated_at')->default(DB::raw('CURRENT_TIMESTAMP'));

            $table->index('tenant_id', 'idx_woa_tenant');
            $table->index(['tenant_id', 'work_order_id'], 'idx_woa_work_order');
            $table->index(['tenant_id', 'user_id'], 'idx_woa_user')
                ->where('user_id IS NOT NULL');
        });

        DB::statement('ALTER TABLE work_order_activities ENABLE ROW LEVEL SECURITY');
        DB::statement('ALTER TABLE work_order_activities FORCE ROW LEVEL SECURITY');

        DB::statement(<<<'SQL'
            CREATE POLICY tenant_isolation_select ON work_order_activities
                FOR SELECT USING (tenant_id = current_tenant_id())
        SQL);

        DB::statement(<<<'SQL'
            CREATE POLICY tenant_isolation_insert ON work_order_activities
                FOR INSERT WITH CHECK (tenant_id = current_tenant_id())
        SQL);

        DB::statement(<<<'SQL'
            CREATE POLICY tenant_isolation_update ON work_order_activities
                FOR UPDATE USING (tenant_id = current_tenant_id())
        SQL);

        DB::statement(<<<'SQL'
            CREATE POLICY tenant_isolation_delete ON work_order_activities
                FOR DELETE USING (tenant_id = current_tenant_id())
        SQL);
    }

    public function down(): void
    {
        Schema::dropIfExists('work_order_activities');
    }
};
