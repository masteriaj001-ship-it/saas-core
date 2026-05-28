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
        Schema::create('locations', function (Blueprint $table) {
            $table->uuid('id')->primary()->default(DB::raw('gen_random_uuid()'));
            $table->foreignUuid('tenant_id')->constrained('tenants')->cascadeOnDelete();
            $table->string('name', 255);
            $table->text('address')->nullable();
            $table->boolean('is_main')->default(false);
            $table->boolean('is_active')->default(true);
            $table->softDeletes();
            $table->timestamps();

            $table->index('tenant_id');
            $table->index(['tenant_id', 'is_main']);
        });

        DB::unprepared('
            ALTER TABLE locations ENABLE ROW LEVEL SECURITY;
            ALTER TABLE locations FORCE ROW LEVEL SECURITY;

            CREATE POLICY "locations_tenant_isolation_select"
                ON locations FOR SELECT
                USING (tenant_id = public.current_tenant_id());

            CREATE POLICY "locations_tenant_isolation_insert"
                ON locations FOR INSERT
                WITH CHECK (tenant_id = public.current_tenant_id());

            CREATE POLICY "locations_tenant_isolation_update"
                ON locations FOR UPDATE
                USING (tenant_id = public.current_tenant_id())
                WITH CHECK (tenant_id = public.current_tenant_id());

            CREATE POLICY "locations_tenant_isolation_delete"
                ON locations FOR DELETE
                USING (tenant_id = public.current_tenant_id());
        ');
    }

    public function down(): void
    {
        DB::unprepared('
            DROP POLICY IF EXISTS "locations_tenant_isolation_delete" ON locations;
            DROP POLICY IF EXISTS "locations_tenant_isolation_update" ON locations;
            DROP POLICY IF EXISTS "locations_tenant_isolation_insert" ON locations;
            DROP POLICY IF EXISTS "locations_tenant_isolation_select" ON locations;
            ALTER TABLE locations FORCE ROW LEVEL SECURITY;
            ALTER TABLE locations DISABLE ROW LEVEL SECURITY;
        ');

        Schema::dropIfExists('locations');
    }
};
