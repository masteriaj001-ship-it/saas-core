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
        Schema::create('work_order_media', function (Blueprint $table): void {
            $table->uuid('id')->primary()->default(DB::raw('gen_random_uuid()'));

            $table->foreignUuid('tenant_id')
                ->constrained('tenants')
                ->cascadeOnDelete();

            $table->foreignUuid('work_order_id')
                ->constrained('work_orders')
                ->cascadeOnDelete();

            $table->foreignUuid('work_order_inspection_id')
                ->nullable()
                ->constrained('work_order_inspections')
                ->nullOnDelete();

            $table->foreignUuid('user_id')
                ->nullable()
                ->constrained('users')
                ->nullOnDelete();

            $table->string('original_name', 255);
            $table->string('storage_path', 500);
            $table->string('mime_type', 127);
            $table->bigInteger('size');
            $table->jsonb('metadata')->default(DB::raw("'{}'::jsonb"));

            $table->timestampTz('created_at')->default(DB::raw('CURRENT_TIMESTAMP'));
            $table->timestampTz('updated_at')->default(DB::raw('CURRENT_TIMESTAMP'));

            $table->index('tenant_id', 'idx_wom_tenant');
            $table->index(['tenant_id', 'work_order_id'], 'idx_wom_work_order');
            $table->index(['tenant_id', 'work_order_inspection_id'], 'idx_wom_inspection')
                ->where('work_order_inspection_id IS NOT NULL');
        });

        DB::statement('ALTER TABLE work_order_media ENABLE ROW LEVEL SECURITY');
        DB::statement('ALTER TABLE work_order_media FORCE ROW LEVEL SECURITY');

        DB::statement(
            'CREATE POLICY tenant_isolation_select ON work_order_media '
            .'FOR SELECT USING (tenant_id = current_tenant_id())'
        );

        DB::statement(
            'CREATE POLICY tenant_isolation_insert ON work_order_media '
            .'FOR INSERT WITH CHECK (tenant_id = current_tenant_id())'
        );

        DB::statement(
            'CREATE POLICY tenant_isolation_update ON work_order_media '
            .'FOR UPDATE USING (tenant_id = current_tenant_id())'
        );

        DB::statement(
            'CREATE POLICY tenant_isolation_delete ON work_order_media '
            .'FOR DELETE USING (tenant_id = current_tenant_id())'
        );
    }

    public function down(): void
    {
        Schema::dropIfExists('work_order_media');
    }
};
