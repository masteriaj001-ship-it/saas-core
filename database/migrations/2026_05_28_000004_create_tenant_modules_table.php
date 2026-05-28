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
        Schema::create('tenant_modules', function (Blueprint $table) {
            $table->uuid('id')->primary()->default(DB::raw('gen_random_uuid()'));
            $table->foreignUuid('tenant_id')->constrained('tenants')->cascadeOnDelete();
            $table->string('module_slug', 100);
            $table->boolean('is_active')->default(true);
            $table->jsonb('config')->default('{}');
            $table->timestampTz('activated_at')->nullable();
            $table->softDeletes();
            $table->timestamps();

            $table->index('tenant_id');
            $table->unique(['tenant_id', 'module_slug']);
        });

        DB::unprepared('
            ALTER TABLE tenant_modules ENABLE ROW LEVEL SECURITY;
            ALTER TABLE tenant_modules FORCE ROW LEVEL SECURITY;

            CREATE POLICY "tenant_modules_tenant_isolation_select"
                ON tenant_modules FOR SELECT
                USING (tenant_id = public.current_tenant_id());

            CREATE POLICY "tenant_modules_tenant_isolation_insert"
                ON tenant_modules FOR INSERT
                WITH CHECK (tenant_id = public.current_tenant_id());

            CREATE POLICY "tenant_modules_tenant_isolation_update"
                ON tenant_modules FOR UPDATE
                USING (tenant_id = public.current_tenant_id())
                WITH CHECK (tenant_id = public.current_tenant_id());

            CREATE POLICY "tenant_modules_tenant_isolation_delete"
                ON tenant_modules FOR DELETE
                USING (tenant_id = public.current_tenant_id());
        ');
    }

    public function down(): void
    {
        DB::unprepared('
            DROP POLICY IF EXISTS "tenant_modules_tenant_isolation_delete" ON tenant_modules;
            DROP POLICY IF EXISTS "tenant_modules_tenant_isolation_update" ON tenant_modules;
            DROP POLICY IF EXISTS "tenant_modules_tenant_isolation_insert" ON tenant_modules;
            DROP POLICY IF EXISTS "tenant_modules_tenant_isolation_select" ON tenant_modules;
            ALTER TABLE tenant_modules FORCE ROW LEVEL SECURITY;
            ALTER TABLE tenant_modules DISABLE ROW LEVEL SECURITY;
        ');

        Schema::dropIfExists('tenant_modules');
    }
};
