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
        Schema::create('item_cost_histories', function (Blueprint $table) {
            $table->uuid('id')->primary()->default(DB::raw('gen_random_uuid()'));
            $table->foreignUuid('tenant_id')->constrained('tenants')->cascadeOnDelete();
            $table->foreignUuid('item_id')->constrained('items')->cascadeOnDelete();
            $table->decimal('previous_cost', 12, 2);
            $table->decimal('new_cost', 12, 2);
            $table->decimal('quantity_affected', 10, 4);
            $table->decimal('stock_before', 10, 4);
            $table->decimal('stock_after', 10, 4);
            $table->string('source_type', 100);
            $table->uuid('source_id');
            $table->timestamp('created_at')->useCurrent();
            $table->timestamp('updated_at')->useCurrent()->useCurrentOnUpdate();
            $table->softDeletes();

            $table->index('tenant_id');
            $table->index(['tenant_id', 'item_id']);
            $table->index(['tenant_id', 'created_at']);
        });

        DB::statement('ALTER TABLE item_cost_histories ENABLE ROW LEVEL SECURITY');
        DB::statement('ALTER TABLE item_cost_histories FORCE ROW LEVEL SECURITY');

        DB::statement(<<<'SQL'
            CREATE POLICY item_cost_histories_tenant_isolation ON item_cost_histories
                FOR SELECT USING (tenant_id = public.current_tenant_id())
        SQL);
        DB::statement(<<<'SQL'
            CREATE POLICY item_cost_histories_insert ON item_cost_histories
                FOR INSERT WITH CHECK (tenant_id = public.current_tenant_id())
        SQL);
    }

    public function down(): void
    {
        DB::statement('DROP POLICY IF EXISTS item_cost_histories_insert ON item_cost_histories');
        DB::statement('DROP POLICY IF EXISTS item_cost_histories_tenant_isolation ON item_cost_histories');
        DB::statement('ALTER TABLE item_cost_histories FORCE ROW LEVEL SECURITY');
        DB::statement('ALTER TABLE item_cost_histories DISABLE ROW LEVEL SECURITY');

        Schema::dropIfExists('item_cost_histories');
    }
};
