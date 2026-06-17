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
        Schema::create('activity_log', function (Blueprint $table) {
            $table->id();
            $table->uuid('tenant_id')->nullable()->constrained('tenants')->cascadeOnDelete();
            $table->string('log_name')->nullable()->index();
            $table->text('description');
            $table->nullableUuidMorphs('subject', 'subject');
            $table->string('event')->nullable();
            $table->nullableUuidMorphs('causer', 'causer');
            $table->json('attribute_changes')->nullable();
            $table->json('properties')->nullable();
            $table->timestamps();
        });

        DB::statement('ALTER TABLE activity_log ENABLE ROW LEVEL SECURITY');
        DB::statement('ALTER TABLE activity_log FORCE ROW LEVEL SECURITY');

        DB::statement(<<<'SQL'
            CREATE POLICY tenant_isolation_select ON activity_log
                FOR SELECT USING (tenant_id = public.current_tenant_id())
        SQL);

        DB::statement(<<<'SQL'
            CREATE POLICY tenant_isolation_insert ON activity_log
                FOR INSERT WITH CHECK (tenant_id = public.current_tenant_id())
        SQL);

        DB::statement(<<<'SQL'
            CREATE POLICY tenant_isolation_update ON activity_log
                FOR UPDATE USING (tenant_id = public.current_tenant_id())
        SQL);

        DB::statement(<<<'SQL'
            CREATE POLICY tenant_isolation_delete ON activity_log
                FOR DELETE USING (tenant_id = public.current_tenant_id())
        SQL);
    }

    public function down(): void
    {
        Schema::dropIfExists('activity_log');
    }
};
