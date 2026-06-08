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
        Schema::table('work_orders', function (Blueprint $table): void {
            $table->integer('mileage_km')->nullable()->after('metadata');
            $table->string('battery_level', 50)->nullable()->after('mileage_km');
            $table->text('aesthetic_notes')->nullable()->after('battery_level');
        });

        DB::statement('CREATE UNIQUE INDEX IF NOT EXISTS idx_work_orders_code_tenant ON work_orders (tenant_id, code) WHERE deleted_at IS NULL');
    }

    public function down(): void
    {
        DB::statement('DROP INDEX IF EXISTS idx_work_orders_code_tenant');

        Schema::table('work_orders', function (Blueprint $table): void {
            $table->dropColumn(['mileage_km', 'battery_level', 'aesthetic_notes']);
        });
    }
};
