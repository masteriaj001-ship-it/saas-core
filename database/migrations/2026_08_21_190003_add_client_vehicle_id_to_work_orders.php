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
        Schema::table('work_orders', function (Blueprint $table) {
            $table->foreignUuid('client_vehicle_id')->nullable()->after('asset_id')->constrained('client_vehicles')->nullOnDelete();
        });

        DB::unprepared('
            CREATE INDEX idx_work_orders_client_vehicle ON work_orders (tenant_id, client_vehicle_id) WHERE deleted_at IS NULL;
        ');
    }

    public function down(): void
    {
        DB::unprepared('
            DROP INDEX IF EXISTS idx_work_orders_client_vehicle;
        ');

        Schema::table('work_orders', function (Blueprint $table) {
            $table->dropForeign(['client_vehicle_id']);
            $table->dropColumn('client_vehicle_id');
        });
    }
};
