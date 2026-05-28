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
        Schema::create('assets', function (Blueprint $table) {
            $table->uuid('id')->primary()->default(DB::raw('gen_random_uuid()'));
            $table->foreignUuid('tenant_id')->constrained('tenants')->cascadeOnDelete();
            $table->string('name', 255);
            $table->string('code', 100)->nullable();
            $table->string('asset_type', 50);
            $table->string('status', 50)->default('active');
            $table->jsonb('metadata')->default('{}');
            $table->date('acquired_at')->nullable();
            $table->date('disposed_at')->nullable();
            $table->softDeletes();
            $table->timestamps();

            $table->index('tenant_id');
            $table->index(['tenant_id', 'status']);
            $table->index(['tenant_id', 'asset_type']);
        });

        DB::unprepared('
            ALTER TABLE assets ENABLE ROW LEVEL SECURITY;
            ALTER TABLE assets FORCE ROW LEVEL SECURITY;

            CREATE POLICY "assets_tenant_isolation_select"
                ON assets FOR SELECT
                USING (tenant_id = public.current_tenant_id());

            CREATE POLICY "assets_tenant_isolation_insert"
                ON assets FOR INSERT
                WITH CHECK (tenant_id = public.current_tenant_id());

            CREATE POLICY "assets_tenant_isolation_update"
                ON assets FOR UPDATE
                USING (tenant_id = public.current_tenant_id())
                WITH CHECK (tenant_id = public.current_tenant_id());

            CREATE POLICY "assets_tenant_isolation_delete"
                ON assets FOR DELETE
                USING (tenant_id = public.current_tenant_id());
        ');
    }

    public function down(): void
    {
        DB::unprepared('
            DROP POLICY IF EXISTS "assets_tenant_isolation_delete" ON assets;
            DROP POLICY IF EXISTS "assets_tenant_isolation_update" ON assets;
            DROP POLICY IF EXISTS "assets_tenant_isolation_insert" ON assets;
            DROP POLICY IF EXISTS "assets_tenant_isolation_select" ON assets;
            ALTER TABLE assets FORCE ROW LEVEL SECURITY;
            ALTER TABLE assets DISABLE ROW LEVEL SECURITY;
        ');

        Schema::dropIfExists('assets');
    }
};
