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
        Schema::create('workshop_bays', function (Blueprint $table) {
            $table->uuid('id')->primary()->default(DB::raw('gen_random_uuid()'));
            $table->foreignUuid('tenant_id')->constrained('tenants')->cascadeOnDelete();
            $table->foreignUuid('location_id')->nullable()->constrained('locations')->nullOnDelete();
            $table->string('code', 20);
            $table->string('name', 100);
            $table->string('type', 50)->default('standard');
            $table->boolean('is_active')->default(true);
            $table->jsonb('metadata')->default('{}');
            $table->foreignUuid('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignUuid('updated_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
            $table->softDeletes();

            $table->unique(['tenant_id', 'code']);
            $table->index('tenant_id');
            $table->index(['tenant_id', 'location_id']);
            $table->index(['tenant_id', 'is_active']);
        });

        DB::statement('ALTER TABLE workshop_bays ENABLE ROW LEVEL SECURITY');
        DB::statement('ALTER TABLE workshop_bays FORCE ROW LEVEL SECURITY');

        DB::statement(<<<'SQL'
            CREATE POLICY workshop_bays_tenant_isolation ON workshop_bays
                FOR SELECT USING (tenant_id = public.current_tenant_id())
        SQL);
        DB::statement(<<<'SQL'
            CREATE POLICY workshop_bays_insert ON workshop_bays
                FOR INSERT WITH CHECK (tenant_id = public.current_tenant_id())
        SQL);
        DB::statement(<<<'SQL'
            CREATE POLICY workshop_bays_update ON workshop_bays
                FOR UPDATE USING (tenant_id = public.current_tenant_id())
        SQL);
        DB::statement(<<<'SQL'
            CREATE POLICY workshop_bays_delete ON workshop_bays
                FOR DELETE USING (tenant_id = public.current_tenant_id())
        SQL);
    }

    public function down(): void
    {
        DB::statement('DROP POLICY IF EXISTS workshop_bays_delete ON workshop_bays');
        DB::statement('DROP POLICY IF EXISTS workshop_bays_update ON workshop_bays');
        DB::statement('DROP POLICY IF EXISTS workshop_bays_insert ON workshop_bays');
        DB::statement('DROP POLICY IF EXISTS workshop_bays_tenant_isolation ON workshop_bays');
        DB::statement('ALTER TABLE workshop_bays FORCE ROW LEVEL SECURITY');
        DB::statement('ALTER TABLE workshop_bays DISABLE ROW LEVEL SECURITY');

        Schema::dropIfExists('workshop_bays');
    }
};
