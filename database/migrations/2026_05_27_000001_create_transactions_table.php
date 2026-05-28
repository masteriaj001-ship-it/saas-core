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
        Schema::create('transactions', function (Blueprint $table) {
            $table->uuid('id')->primary()->default(DB::raw('gen_random_uuid()'));
            $table->foreignUuid('tenant_id')->constrained('tenants')->cascadeOnDelete();
            $table->foreignUuid('contact_id')->constrained('contacts')->cascadeOnDelete();
            $table->string('type', 20);
            $table->string('invoice_number', 50);
            $table->string('cufe', 100)->nullable();
            $table->string('resolution_number', 50)->nullable();
            $table->string('status', 20)->default('draft');
            $table->decimal('subtotal', 14, 2)->default(0);
            $table->decimal('total_tax', 14, 2)->default(0);
            $table->decimal('total_retentions', 14, 2)->default(0);
            $table->decimal('total_amount', 14, 2)->default(0);
            $table->string('payment_method', 20)->nullable();
            $table->text('notes')->nullable();
            $table->foreignUuid('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->softDeletes();
            $table->timestamps();

            $table->index('tenant_id');
            $table->index(['tenant_id', 'type']);
            $table->index(['tenant_id', 'status']);
            $table->unique(['tenant_id', 'invoice_number']);
        });

        DB::unprepared('
            ALTER TABLE transactions ENABLE ROW LEVEL SECURITY;
            ALTER TABLE transactions FORCE ROW LEVEL SECURITY;

            CREATE POLICY "transactions_tenant_isolation_select"
                ON transactions FOR SELECT
                USING (tenant_id = public.current_tenant_id());

            CREATE POLICY "transactions_tenant_isolation_insert"
                ON transactions FOR INSERT
                WITH CHECK (tenant_id = public.current_tenant_id());

            CREATE POLICY "transactions_tenant_isolation_update"
                ON transactions FOR UPDATE
                USING (tenant_id = public.current_tenant_id())
                WITH CHECK (tenant_id = public.current_tenant_id());

            CREATE POLICY "transactions_tenant_isolation_delete"
                ON transactions FOR DELETE
                USING (tenant_id = public.current_tenant_id());
        ');

        Schema::create('transaction_items', function (Blueprint $table) {
            $table->uuid('id')->primary()->default(DB::raw('gen_random_uuid()'));
            $table->foreignUuid('tenant_id')->constrained('tenants')->cascadeOnDelete();
            $table->foreignUuid('transaction_id')->constrained('transactions')->cascadeOnDelete();
            $table->foreignUuid('item_id')->constrained('items')->cascadeOnDelete();
            $table->decimal('quantity', 12, 4)->default(1);
            $table->decimal('unit_price', 14, 4)->default(0);
            $table->decimal('tax_rate', 5, 2)->default(0);
            $table->decimal('tax_amount', 14, 2)->default(0);
            $table->decimal('discount_amount', 14, 2)->default(0);
            $table->decimal('total_item_amount', 14, 2)->default(0);
            $table->softDeletes();
            $table->timestamps();

            $table->index('tenant_id');
            $table->index('transaction_id');
            $table->index(['tenant_id', 'item_id']);
        });

        DB::unprepared('
            ALTER TABLE transaction_items ENABLE ROW LEVEL SECURITY;
            ALTER TABLE transaction_items FORCE ROW LEVEL SECURITY;

            CREATE POLICY "transaction_items_tenant_isolation_select"
                ON transaction_items FOR SELECT
                USING (tenant_id = public.current_tenant_id());

            CREATE POLICY "transaction_items_tenant_isolation_insert"
                ON transaction_items FOR INSERT
                WITH CHECK (tenant_id = public.current_tenant_id());

            CREATE POLICY "transaction_items_tenant_isolation_update"
                ON transaction_items FOR UPDATE
                USING (tenant_id = public.current_tenant_id())
                WITH CHECK (tenant_id = public.current_tenant_id());

            CREATE POLICY "transaction_items_tenant_isolation_delete"
                ON transaction_items FOR DELETE
                USING (tenant_id = public.current_tenant_id());
        ');
    }

    public function down(): void
    {
        DB::unprepared('
            DROP POLICY IF EXISTS "transaction_items_tenant_isolation_delete" ON transaction_items;
            DROP POLICY IF EXISTS "transaction_items_tenant_isolation_update" ON transaction_items;
            DROP POLICY IF EXISTS "transaction_items_tenant_isolation_insert" ON transaction_items;
            DROP POLICY IF EXISTS "transaction_items_tenant_isolation_select" ON transaction_items;
            ALTER TABLE transaction_items FORCE ROW LEVEL SECURITY;
            ALTER TABLE transaction_items DISABLE ROW LEVEL SECURITY;
        ');
        Schema::dropIfExists('transaction_items');

        DB::unprepared('
            DROP POLICY IF EXISTS "transactions_tenant_isolation_delete" ON transactions;
            DROP POLICY IF EXISTS "transactions_tenant_isolation_update" ON transactions;
            DROP POLICY IF EXISTS "transactions_tenant_isolation_insert" ON transactions;
            DROP POLICY IF EXISTS "transactions_tenant_isolation_select" ON transactions;
            ALTER TABLE transactions FORCE ROW LEVEL SECURITY;
            ALTER TABLE transactions DISABLE ROW LEVEL SECURITY;
        ');
        Schema::dropIfExists('transactions');
    }
};
