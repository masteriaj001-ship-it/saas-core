<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('work_order_items', function (Blueprint $table) {
            $table->decimal('unit_cost_at_sale', 12, 2)->nullable()->after('unit_price');
            $table->foreignUuid('stock_movement_id')->nullable()->constrained('stock_movements')->nullOnDelete()->after('unit_cost_at_sale');
        });
    }

    public function down(): void
    {
        Schema::table('work_order_items', function (Blueprint $table) {
            $table->dropForeign(['stock_movement_id']);
            $table->dropColumn(['unit_cost_at_sale', 'stock_movement_id']);
        });
    }
};
