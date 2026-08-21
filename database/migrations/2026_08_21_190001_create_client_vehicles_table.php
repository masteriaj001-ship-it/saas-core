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
        Schema::create('client_vehicles', function (Blueprint $table) {
            $table->uuid('id')->primary()->default(DB::raw('gen_random_uuid()'));
            $table->foreignUuid('tenant_id')->constrained('tenants')->cascadeOnDelete();
            $table->foreignUuid('owner_contact_id')->nullable()->constrained('contacts')->nullOnDelete();
            $table->string('plate', 20)->nullable();
            $table->string('brand', 100)->nullable();
            $table->string('model', 100)->nullable();
            $table->string('version', 100)->nullable();
            $table->integer('year')->nullable();
            $table->string('vin', 100)->nullable();
            $table->string('engine_number', 100)->nullable();
            $table->string('color', 50)->nullable();
            $table->string('fuel_type', 50)->nullable();
            $table->string('vehicle_type', 50)->nullable();
            $table->integer('current_mileage')->nullable();
            $table->text('notes')->nullable();
            $table->jsonb('metadata')->default('{}');
            $table->softDeletes();
            $table->timestamps();

            $table->index('tenant_id');
            $table->index(['tenant_id', 'owner_contact_id']);
        });

        DB::unprepared('
            CREATE UNIQUE INDEX idx_cv_tenant_plate ON client_vehicles (tenant_id, plate) WHERE deleted_at IS NULL AND plate IS NOT NULL;
            CREATE UNIQUE INDEX idx_cv_tenant_vin ON client_vehicles (tenant_id, vin) WHERE deleted_at IS NULL AND vin IS NOT NULL;
        ');

        DB::unprepared('
            ALTER TABLE client_vehicles ENABLE ROW LEVEL SECURITY;
            ALTER TABLE client_vehicles FORCE ROW LEVEL SECURITY;

            CREATE POLICY "client_vehicles_tenant_isolation_select"
                ON client_vehicles FOR SELECT
                USING (tenant_id = public.current_tenant_id());

            CREATE POLICY "client_vehicles_tenant_isolation_insert"
                ON client_vehicles FOR INSERT
                WITH CHECK (tenant_id = public.current_tenant_id());

            CREATE POLICY "client_vehicles_tenant_isolation_update"
                ON client_vehicles FOR UPDATE
                USING (tenant_id = public.current_tenant_id())
                WITH CHECK (tenant_id = public.current_tenant_id());

            CREATE POLICY "client_vehicles_tenant_isolation_delete"
                ON client_vehicles FOR DELETE
                USING (tenant_id = public.current_tenant_id());
        ');
    }

    public function down(): void
    {
        DB::unprepared('
            DROP POLICY IF EXISTS "client_vehicles_tenant_isolation_delete" ON client_vehicles;
            DROP POLICY IF EXISTS "client_vehicles_tenant_isolation_update" ON client_vehicles;
            DROP POLICY IF EXISTS "client_vehicles_tenant_isolation_insert" ON client_vehicles;
            DROP POLICY IF EXISTS "client_vehicles_tenant_isolation_select" ON client_vehicles;
            ALTER TABLE client_vehicles FORCE ROW LEVEL SECURITY;
            ALTER TABLE client_vehicles DISABLE ROW LEVEL SECURITY;
        ');

        Schema::dropIfExists('client_vehicles');
    }
};
