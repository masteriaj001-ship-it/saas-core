<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('invoice_payments', function (Blueprint $table): void {
            $table->uuid('id')->primary();
            $table->foreignUuid('tenant_id')->constrained()->cascadeOnDelete();
            $table->foreignUuid('invoice_id')->constrained()->cascadeOnDelete();
            $table->string('payment_method', 30);
            $table->decimal('amount', 12, 2)->default(0);
            $table->decimal('cash_received', 12, 2)->nullable();
            $table->decimal('change_due', 12, 2)->nullable();
            $table->string('reference')->nullable();
            $table->timestampTz('paid_at')->nullable();
            $table->timestamps();
        });

        DB::statement('ALTER TABLE invoice_payments ENABLE ROW LEVEL SECURITY');
        DB::statement('ALTER TABLE invoice_payments FORCE ROW LEVEL SECURITY');

        DB::statement('CREATE INDEX IF NOT EXISTS idx_invoice_payments_invoice ON invoice_payments (tenant_id, invoice_id)');
        DB::statement('CREATE INDEX IF NOT EXISTS idx_invoice_payments_paid_at ON invoice_payments (tenant_id, paid_at)');
    }

    public function down(): void
    {
        Schema::dropIfExists('invoice_payments');
    }
};
