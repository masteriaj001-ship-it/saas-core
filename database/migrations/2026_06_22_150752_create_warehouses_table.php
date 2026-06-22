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
        Schema::create('warehouses', function (Blueprint $table) {
            $table->uuid('id')->primary()->default(DB::raw('gen_random_uuid()'));
            $table->foreignUuid('tenant_id')->constrained('tenants')->cascadeOnDelete();
            $table->foreignUuid('location_id')->nullable()->constrained('locations')->nullOnDelete();
            $table->string('code', 30);
            $table->string('name', 255);
            $table->text('address')->nullable();
            $table->boolean('is_default')->default(false);
            $table->boolean('is_active')->default(true);
            $table->jsonb('metadata')->default('{}');
            $table->softDeletes();
            $table->timestamps();

            $table->index('tenant_id');
            $table->unique(['tenant_id', 'code']);
            $table->index(['tenant_id', 'is_default']);
        });

        DB::statement('ALTER TABLE warehouses ENABLE ROW LEVEL SECURITY');
        DB::statement('ALTER TABLE warehouses FORCE ROW LEVEL SECURITY');

        DB::statement(<<<'SQL'
            CREATE POLICY tenant_isolation_select ON warehouses
                FOR SELECT USING (tenant_id = public.current_tenant_id())
        SQL);
        DB::statement(<<<'SQL'
            CREATE POLICY tenant_isolation_insert ON warehouses
                FOR INSERT WITH CHECK (tenant_id = public.current_tenant_id())
        SQL);
        DB::statement(<<<'SQL'
            CREATE POLICY tenant_isolation_update ON warehouses
                FOR UPDATE USING (tenant_id = public.current_tenant_id())
        SQL);
        DB::statement(<<<'SQL'
            CREATE POLICY tenant_isolation_delete ON warehouses
                FOR DELETE USING (tenant_id = public.current_tenant_id())
        SQL);
    }

    public function down(): void
    {
        DB::statement('DROP POLICY IF EXISTS tenant_isolation_delete ON warehouses');
        DB::statement('DROP POLICY IF EXISTS tenant_isolation_update ON warehouses');
        DB::statement('DROP POLICY IF EXISTS tenant_isolation_insert ON warehouses');
        DB::statement('DROP POLICY IF EXISTS tenant_isolation_select ON warehouses');
        DB::statement('ALTER TABLE warehouses FORCE ROW LEVEL SECURITY');
        DB::statement('ALTER TABLE warehouses DISABLE ROW LEVEL SECURITY');

        Schema::dropIfExists('warehouses');
    }
};
