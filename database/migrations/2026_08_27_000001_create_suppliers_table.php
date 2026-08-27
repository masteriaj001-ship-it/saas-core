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
        Schema::create('suppliers', function (Blueprint $table) {
            $table->uuid('id')->primary()->default(DB::raw('gen_random_uuid()'));
            $table->foreignUuid('tenant_id')->constrained('tenants')->cascadeOnDelete();
            $table->foreignUuid('contact_id')->constrained('contacts')->restrictOnDelete();
            $table->string('code', 30);
            $table->string('trade_name', 100)->nullable();
            $table->integer('payment_terms_days')->default(30);
            $table->decimal('credit_limit', 12, 2)->default(0);
            $table->integer('lead_time_days')->default(7);
            $table->text('notes')->nullable();
            $table->boolean('is_active')->default(true);
            $table->jsonb('metadata')->default('{}');
            $table->foreignUuid('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignUuid('updated_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
            $table->softDeletes();

            $table->unique(['tenant_id', 'code']);
            $table->index('tenant_id');
            $table->index(['tenant_id', 'contact_id']);
            $table->index(['tenant_id', 'is_active']);
        });

        DB::statement('ALTER TABLE suppliers ENABLE ROW LEVEL SECURITY');
        DB::statement('ALTER TABLE suppliers FORCE ROW LEVEL SECURITY');

        DB::statement(<<<'SQL'
            CREATE POLICY suppliers_tenant_isolation ON suppliers
                FOR SELECT USING (tenant_id = public.current_tenant_id())
        SQL);
        DB::statement(<<<'SQL'
            CREATE POLICY suppliers_insert ON suppliers
                FOR INSERT WITH CHECK (tenant_id = public.current_tenant_id())
        SQL);
        DB::statement(<<<'SQL'
            CREATE POLICY suppliers_update ON suppliers
                FOR UPDATE USING (tenant_id = public.current_tenant_id())
        SQL);
        DB::statement(<<<'SQL'
            CREATE POLICY suppliers_delete ON suppliers
                FOR DELETE USING (tenant_id = public.current_tenant_id())
        SQL);
    }

    public function down(): void
    {
        DB::statement('DROP POLICY IF EXISTS suppliers_delete ON suppliers');
        DB::statement('DROP POLICY IF EXISTS suppliers_update ON suppliers');
        DB::statement('DROP POLICY IF EXISTS suppliers_insert ON suppliers');
        DB::statement('DROP POLICY IF EXISTS suppliers_tenant_isolation ON suppliers');
        DB::statement('ALTER TABLE suppliers FORCE ROW LEVEL SECURITY');
        DB::statement('ALTER TABLE suppliers DISABLE ROW LEVEL SECURITY');

        Schema::dropIfExists('suppliers');
    }
};
