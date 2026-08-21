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
        Schema::create('vehicle_mileage_logs', function (Blueprint $table) {
            $table->uuid('id')->primary()->default(DB::raw('gen_random_uuid()'));
            $table->foreignUuid('tenant_id')->constrained('tenants')->cascadeOnDelete();
            $table->foreignUuid('client_vehicle_id')->constrained('client_vehicles')->cascadeOnDelete();
            $table->foreignUuid('work_order_id')->nullable()->constrained('work_orders')->nullOnDelete();
            $table->integer('mileage');
            $table->timestamp('recorded_at');
            $table->text('notes')->nullable();
            $table->timestamps();

            $table->index('tenant_id');
            $table->index(['tenant_id', 'client_vehicle_id']);
            $table->index(['tenant_id', 'work_order_id']);
        });

        DB::unprepared('
            ALTER TABLE vehicle_mileage_logs ENABLE ROW LEVEL SECURITY;
            ALTER TABLE vehicle_mileage_logs FORCE ROW LEVEL SECURITY;

            CREATE POLICY "vehicle_mileage_logs_tenant_isolation_select"
                ON vehicle_mileage_logs FOR SELECT
                USING (tenant_id = public.current_tenant_id());

            CREATE POLICY "vehicle_mileage_logs_tenant_isolation_insert"
                ON vehicle_mileage_logs FOR INSERT
                WITH CHECK (tenant_id = public.current_tenant_id());

            CREATE POLICY "vehicle_mileage_logs_tenant_isolation_update"
                ON vehicle_mileage_logs FOR UPDATE
                USING (tenant_id = public.current_tenant_id())
                WITH CHECK (tenant_id = public.current_tenant_id());

            CREATE POLICY "vehicle_mileage_logs_tenant_isolation_delete"
                ON vehicle_mileage_logs FOR DELETE
                USING (tenant_id = public.current_tenant_id());
        ');
    }

    public function down(): void
    {
        DB::unprepared('
            DROP POLICY IF EXISTS "vehicle_mileage_logs_tenant_isolation_delete" ON vehicle_mileage_logs;
            DROP POLICY IF EXISTS "vehicle_mileage_logs_tenant_isolation_update" ON vehicle_mileage_logs;
            DROP POLICY IF EXISTS "vehicle_mileage_logs_tenant_isolation_insert" ON vehicle_mileage_logs;
            DROP POLICY IF EXISTS "vehicle_mileage_logs_tenant_isolation_select" ON vehicle_mileage_logs;
            ALTER TABLE vehicle_mileage_logs FORCE ROW LEVEL SECURITY;
            ALTER TABLE vehicle_mileage_logs DISABLE ROW LEVEL SECURITY;
        ');

        Schema::dropIfExists('vehicle_mileage_logs');
    }
};
