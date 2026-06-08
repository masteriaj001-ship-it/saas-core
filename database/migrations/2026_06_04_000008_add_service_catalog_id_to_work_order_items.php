<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('work_order_items', function (Blueprint $table): void {
            $table->foreignUuid('service_catalog_id')
                ->nullable()
                ->after('item_id')
                ->constrained('service_catalogs')
                ->nullOnDelete();

            $table->index(['tenant_id', 'service_catalog_id'], 'idx_woi_service_catalog');
        });
    }

    public function down(): void
    {
        Schema::table('work_order_items', function (Blueprint $table): void {
            $table->dropIndex('idx_woi_service_catalog');
            $table->dropForeign(['service_catalog_id']);
            $table->dropColumn('service_catalog_id');
        });
    }
};
