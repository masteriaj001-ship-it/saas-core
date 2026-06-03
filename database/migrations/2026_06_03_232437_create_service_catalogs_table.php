<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('service_catalogs', function (Blueprint $table) {
            $table->uuid('id')->primary()->default(DB::raw('gen_random_uuid()'));
            $table->foreignUuid('tenant_id')->constrained('tenants')->cascadeOnDelete();
            $table->string('name', 255);
            $table->text('description')->nullable();
            $table->decimal('base_price', 10, 2)->default(0);
            $table->unsignedInteger('estimated_minutes')->nullable();
            $table->boolean('is_active')->default(true);
            $table->softDeletes();
            $table->timestamps();

            $table->index('tenant_id');
            $table->unique(['tenant_id', 'name'], 'idx_service_catalogs_tenant_name')->whereNull('deleted_at');
            $table->index(['tenant_id', 'is_active'], 'idx_service_catalogs_tenant_active');
        });

        DB::unprepared('
            ALTER TABLE service_catalogs ENABLE ROW LEVEL SECURITY;
            ALTER TABLE service_catalogs FORCE ROW LEVEL SECURITY;

            CREATE POLICY "service_catalogs_tenant_isolation_select"
                ON service_catalogs FOR SELECT
                USING (tenant_id = public.current_tenant_id());

            CREATE POLICY "service_catalogs_tenant_isolation_insert"
                ON service_catalogs FOR INSERT
                WITH CHECK (tenant_id = public.current_tenant_id());

            CREATE POLICY "service_catalogs_tenant_isolation_update"
                ON service_catalogs FOR UPDATE
                USING (tenant_id = public.current_tenant_id())
                WITH CHECK (tenant_id = public.current_tenant_id());

            CREATE POLICY "service_catalogs_tenant_isolation_delete"
                ON service_catalogs FOR DELETE
                USING (tenant_id = public.current_tenant_id());
        ');
    }

    public function down(): void
    {
        DB::unprepared('
            DROP POLICY IF EXISTS "service_catalogs_tenant_isolation_delete" ON service_catalogs;
            DROP POLICY IF EXISTS "service_catalogs_tenant_isolation_update" ON service_catalogs;
            DROP POLICY IF EXISTS "service_catalogs_tenant_isolation_insert" ON service_catalogs;
            DROP POLICY IF EXISTS "service_catalogs_tenant_isolation_select" ON service_catalogs;
            ALTER TABLE service_catalogs FORCE ROW LEVEL SECURITY;
            ALTER TABLE service_catalogs DISABLE ROW LEVEL SECURITY;
        ');

        Schema::dropIfExists('service_catalogs');
    }
};
