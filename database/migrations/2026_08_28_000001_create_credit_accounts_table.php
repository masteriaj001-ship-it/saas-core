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
        Schema::create('credit_accounts', function (Blueprint $table) {
            $table->uuid('id')->primary()->default(DB::raw('gen_random_uuid()'));
            $table->foreignUuid('tenant_id')->constrained('tenants')->cascadeOnDelete();
            $table->foreignUuid('contact_id')->constrained('contacts')->restrictOnDelete();
            $table->decimal('credit_limit', 12, 2)->default(0)->check('credit_limit >= 0');
            $table->decimal('current_balance', 12, 2)->default(0);
            $table->integer('payment_terms_days')->default(30);
            $table->boolean('is_active')->default(true);
            $table->text('notes')->nullable();
            $table->jsonb('metadata')->default('{}');
            $table->foreignUuid('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->softDeletes();
            $table->timestamps();

            $table->unique(['tenant_id', 'contact_id']);
            $table->index('tenant_id');
            $table->index('contact_id');
        });

        DB::statement('ALTER TABLE credit_accounts ENABLE ROW LEVEL SECURITY');
        DB::statement('ALTER TABLE credit_accounts FORCE ROW LEVEL SECURITY');

        DB::statement(<<<'SQL'
            CREATE POLICY credit_accounts_tenant_isolation ON credit_accounts
                FOR ALL USING (tenant_id = public.current_tenant_id())
        SQL);

        DB::statement(<<<'SQL'
            CREATE POLICY credit_accounts_insert ON credit_accounts
                FOR INSERT WITH CHECK (tenant_id = public.current_tenant_id())
        SQL);

        DB::statement(<<<'SQL'
            CREATE POLICY credit_accounts_update ON credit_accounts
                FOR UPDATE USING (tenant_id = public.current_tenant_id())
        SQL);

        DB::statement(<<<'SQL'
            CREATE POLICY credit_accounts_delete ON credit_accounts
                FOR DELETE USING (tenant_id = public.current_tenant_id())
        SQL);
    }

    public function down(): void
    {
        DB::statement('DROP POLICY IF EXISTS credit_accounts_delete ON credit_accounts');
        DB::statement('DROP POLICY IF EXISTS credit_accounts_update ON credit_accounts');
        DB::statement('DROP POLICY IF EXISTS credit_accounts_insert ON credit_accounts');
        DB::statement('DROP POLICY IF EXISTS credit_accounts_tenant_isolation ON credit_accounts');
        DB::statement('ALTER TABLE credit_accounts FORCE ROW LEVEL SECURITY');
        DB::statement('ALTER TABLE credit_accounts DISABLE ROW LEVEL SECURITY');

        Schema::dropIfExists('credit_accounts');
    }
};
