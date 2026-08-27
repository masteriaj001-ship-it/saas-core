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
        Schema::create('credit_transactions', function (Blueprint $table) {
            $table->uuid('id')->primary()->default(DB::raw('gen_random_uuid()'));
            $table->foreignUuid('tenant_id')->constrained('tenants')->cascadeOnDelete();
            $table->foreignUuid('credit_account_id')->constrained('credit_accounts')->restrictOnDelete();
            $table->string('type', 30)->check('type IN (\'charge\', \'payment\', \'charge_reverse\', \'payment_reversal\')');
            $table->decimal('amount', 12, 2)->check('amount > 0');
            $table->date('due_date')->nullable();
            $table->timestampTz('paid_at')->nullable();
            $table->foreignUuid('invoice_id')->nullable()->constrained('invoices')->nullOnDelete();
            $table->string('reference', 255)->nullable();
            $table->text('notes')->nullable();
            $table->ipAddress('ip_address')->nullable();
            $table->jsonb('metadata')->default('{}');
            $table->foreignUuid('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->softDeletes();
            $table->timestamps();

            $table->index('tenant_id');
            $table->index('credit_account_id');
            $table->index('type');
            $table->index('invoice_id');
            $table->index('due_date');
            $table->index('created_at');
        });

        DB::statement('ALTER TABLE credit_transactions ENABLE ROW LEVEL SECURITY');
        DB::statement('ALTER TABLE credit_transactions FORCE ROW LEVEL SECURITY');

        DB::statement(<<<'SQL'
            CREATE POLICY credit_transactions_tenant_isolation ON credit_transactions
                FOR ALL USING (tenant_id = public.current_tenant_id())
        SQL);

        DB::statement(<<<'SQL'
            CREATE POLICY credit_transactions_insert ON credit_transactions
                FOR INSERT WITH CHECK (tenant_id = public.current_tenant_id())
        SQL);
    }

    public function down(): void
    {
        DB::statement('DROP POLICY IF EXISTS credit_transactions_insert ON credit_transactions');
        DB::statement('DROP POLICY IF EXISTS credit_transactions_tenant_isolation ON credit_transactions');
        DB::statement('ALTER TABLE credit_transactions FORCE ROW LEVEL SECURITY');
        DB::statement('ALTER TABLE credit_transactions DISABLE ROW LEVEL SECURITY');

        Schema::dropIfExists('credit_transactions');
    }
};
