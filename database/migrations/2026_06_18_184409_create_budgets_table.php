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
        Schema::create('budgets', function (Blueprint $table): void {
            $table->uuid('id')->primary()->default(DB::raw('gen_random_uuid()'));
            $table->uuid('tenant_id')->notNullable();
            $table->string('code', 50)->notNullable();
            $table->uuid('contact_id')->nullable();
            $table->string('contact_name', 255)->notNullable();
            $table->string('contact_phone', 40)->nullable();
            $table->string('contact_email', 255)->nullable();
            $table->jsonb('vehicle_data')->notNullable()->default('{}');
            $table->string('status', 20)->notNullable()->default('draft');
            $table->decimal('subtotal', 14, 2)->notNullable()->default(0);
            $table->decimal('discount_total', 14, 2)->notNullable()->default(0);
            $table->decimal('tax_total', 14, 2)->notNullable()->default(0);
            $table->decimal('grand_total', 14, 2)->notNullable()->default(0);
            $table->text('notes')->nullable();
            $table->timestampTz('sent_at')->nullable();
            $table->timestampTz('responded_at')->nullable();
            $table->timestampTz('approved_at')->nullable();
            $table->timestampTz('rejected_at')->nullable();
            $table->text('rejection_reason')->nullable();
            $table->uuid('converted_to_work_order_id')->nullable();
            $table->uuid('created_by')->nullable();
            $table->timestampsTz();
            $table->softDeletesTz();

            $table->unique(['tenant_id', 'code']);

            $table->foreign('tenant_id')->references('id')->on('tenants')->onDelete('cascade');
            $table->foreign('contact_id')->references('id')->on('contacts')->onDelete('set null');
            $table->foreign('converted_to_work_order_id')->references('id')->on('work_orders')->onDelete('set null');
            $table->foreign('created_by')->references('id')->on('users')->onDelete('set null');
        });

        DB::statement('ALTER TABLE budgets ENABLE ROW LEVEL SECURITY');
        DB::statement('CREATE POLICY tenant_isolation_budgets ON budgets USING (tenant_id = current_tenant_id())');
    }

    public function down(): void
    {
        Schema::dropIfExists('budgets');
    }
};
