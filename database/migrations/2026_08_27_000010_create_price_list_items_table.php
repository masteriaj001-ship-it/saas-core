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
        Schema::create('price_list_items', function (Blueprint $table) {
            $table->uuid('id')->primary()->default(DB::raw('gen_random_uuid()'));
            $table->foreignUuid('tenant_id')->constrained('tenants')->cascadeOnDelete();
            $table->foreignUuid('price_list_id')->constrained('price_lists')->cascadeOnDelete();
            $table->foreignUuid('item_id')->constrained('items')->restrictOnDelete();
            $table->decimal('price', 12, 2);
            $table->integer('min_quantity')->default(1);
            $table->jsonb('metadata')->default('{}');
            $table->timestamps();
            $table->softDeletes();

            $table->unique(['tenant_id', 'price_list_id', 'item_id']);
            $table->index('tenant_id');
            $table->index(['tenant_id', 'price_list_id']);
            $table->index(['tenant_id', 'item_id']);
        });

        DB::statement('ALTER TABLE price_list_items ENABLE ROW LEVEL SECURITY');
        DB::statement('ALTER TABLE price_list_items FORCE ROW LEVEL SECURITY');

        DB::statement(<<<'SQL'
            CREATE POLICY price_list_items_tenant_isolation ON price_list_items
                FOR SELECT USING (tenant_id = public.current_tenant_id())
        SQL);
        DB::statement(<<<'SQL'
            CREATE POLICY price_list_items_insert ON price_list_items
                FOR INSERT WITH CHECK (tenant_id = public.current_tenant_id())
        SQL);
        DB::statement(<<<'SQL'
            CREATE POLICY price_list_items_update ON price_list_items
                FOR UPDATE USING (tenant_id = public.current_tenant_id())
        SQL);
        DB::statement(<<<'SQL'
            CREATE POLICY price_list_items_delete ON price_list_items
                FOR DELETE USING (tenant_id = public.current_tenant_id())
        SQL);
    }

    public function down(): void
    {
        DB::statement('DROP POLICY IF EXISTS price_list_items_delete ON price_list_items');
        DB::statement('DROP POLICY IF EXISTS price_list_items_update ON price_list_items');
        DB::statement('DROP POLICY IF EXISTS price_list_items_insert ON price_list_items');
        DB::statement('DROP POLICY IF EXISTS price_list_items_tenant_isolation ON price_list_items');
        DB::statement('ALTER TABLE price_list_items FORCE ROW LEVEL SECURITY');
        DB::statement('ALTER TABLE price_list_items DISABLE ROW LEVEL SECURITY');

        Schema::dropIfExists('price_list_items');
    }
};
