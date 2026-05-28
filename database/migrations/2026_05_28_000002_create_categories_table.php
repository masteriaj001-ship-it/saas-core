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
        Schema::create('categories', function (Blueprint $table) {
            $table->uuid('id')->primary()->default(DB::raw('gen_random_uuid()'));
            $table->foreignUuid('tenant_id')->constrained('tenants')->cascadeOnDelete();
            $table->string('name', 255);
            $table->text('description')->nullable();
            $table->boolean('is_active')->default(true);
            $table->softDeletes();
            $table->timestamps();

            $table->index('tenant_id');
            $table->index(['tenant_id', 'is_active']);
        });

        DB::unprepared('
            ALTER TABLE categories ENABLE ROW LEVEL SECURITY;
            ALTER TABLE categories FORCE ROW LEVEL SECURITY;

            CREATE POLICY "categories_tenant_isolation_select"
                ON categories FOR SELECT
                USING (tenant_id = public.current_tenant_id());

            CREATE POLICY "categories_tenant_isolation_insert"
                ON categories FOR INSERT
                WITH CHECK (tenant_id = public.current_tenant_id());

            CREATE POLICY "categories_tenant_isolation_update"
                ON categories FOR UPDATE
                USING (tenant_id = public.current_tenant_id())
                WITH CHECK (tenant_id = public.current_tenant_id());

            CREATE POLICY "categories_tenant_isolation_delete"
                ON categories FOR DELETE
                USING (tenant_id = public.current_tenant_id());
        ');
    }

    public function down(): void
    {
        DB::unprepared('
            DROP POLICY IF EXISTS "categories_tenant_isolation_delete" ON categories;
            DROP POLICY IF EXISTS "categories_tenant_isolation_update" ON categories;
            DROP POLICY IF EXISTS "categories_tenant_isolation_insert" ON categories;
            DROP POLICY IF EXISTS "categories_tenant_isolation_select" ON categories;
            ALTER TABLE categories FORCE ROW LEVEL SECURITY;
            ALTER TABLE categories DISABLE ROW LEVEL SECURITY;
        ');

        Schema::dropIfExists('categories');
    }
};
