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
        Schema::create('price_lists', function (Blueprint $table) {
            $table->uuid('id')->primary()->default(DB::raw('gen_random_uuid()'));
            $table->foreignUuid('tenant_id')->constrained('tenants')->cascadeOnDelete();
            $table->string('name', 100);
            $table->text('description')->nullable();
            $table->boolean('is_default')->default(false);
            $table->boolean('is_active')->default(true);
            $table->jsonb('metadata')->default('{}');
            $table->foreignUuid('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignUuid('updated_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
            $table->softDeletes();

            $table->unique(['tenant_id', 'name']);
            $table->index('tenant_id');
            $table->index(['tenant_id', 'is_default']);
        });

        DB::statement('ALTER TABLE price_lists ENABLE ROW LEVEL SECURITY');
        DB::statement('ALTER TABLE price_lists FORCE ROW LEVEL SECURITY');

        DB::statement(<<<'SQL'
            CREATE POLICY price_lists_tenant_isolation ON price_lists
                FOR SELECT USING (tenant_id = public.current_tenant_id())
        SQL);
        DB::statement(<<<'SQL'
            CREATE POLICY price_lists_insert ON price_lists
                FOR INSERT WITH CHECK (tenant_id = public.current_tenant_id())
        SQL);
        DB::statement(<<<'SQL'
            CREATE POLICY price_lists_update ON price_lists
                FOR UPDATE USING (tenant_id = public.current_tenant_id())
        SQL);
        DB::statement(<<<'SQL'
            CREATE POLICY price_lists_delete ON price_lists
                FOR DELETE USING (tenant_id = public.current_tenant_id())
        SQL);
    }

    public function down(): void
    {
        DB::statement('DROP POLICY IF EXISTS price_lists_delete ON price_lists');
        DB::statement('DROP POLICY IF EXISTS price_lists_update ON price_lists');
        DB::statement('DROP POLICY IF EXISTS price_lists_insert ON price_lists');
        DB::statement('DROP POLICY IF EXISTS price_lists_tenant_isolation ON price_lists');
        DB::statement('ALTER TABLE price_lists FORCE ROW LEVEL SECURITY');
        DB::statement('ALTER TABLE price_lists DISABLE ROW LEVEL SECURITY');

        Schema::dropIfExists('price_lists');
    }
};
