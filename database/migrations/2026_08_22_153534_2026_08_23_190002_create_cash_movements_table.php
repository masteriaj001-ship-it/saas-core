<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create('cash_movements', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->uuid('tenant_id')->index();
            $table->uuid('shift_id')->index();
            $table->uuid('work_order_id')->nullable()->index();
            $table->uuid('invoice_id')->nullable()->index();
            $table->enum('type', ['sale', 'expense', 'income', 'refund']);
            $table->enum('payment_method', ['cash', 'card', 'transfer', 'nequi', 'daviplata', 'other']);
            $table->decimal('amount', 12, 2);
            $table->text('description');
            $table->uuid('created_by')->index();
            $table->timestamps();

            $table->foreign('tenant_id')->references('id')->on('tenants')->onDelete('cascade');
            $table->foreign('shift_id')->references('id')->on('cash_shifts')->onDelete('cascade');
            $table->foreign('work_order_id')->references('id')->on('work_orders')->onDelete('cascade');
            $table->foreign('invoice_id')->references('id')->on('invoices')->onDelete('cascade');
            $table->foreign('created_by')->references('id')->on('users')->onDelete('cascade');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('cash_movements');
    }
};
