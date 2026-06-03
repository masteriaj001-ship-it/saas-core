<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('assets', function (Blueprint $table) {
            $table->string('plate', 20)->nullable()->after('code');
            $table->string('brand', 100)->nullable()->after('plate');
            $table->string('model', 100)->nullable()->after('brand');
            $table->integer('year')->nullable()->after('model');
        });

        DB::statement('CREATE UNIQUE INDEX IF NOT EXISTS idx_assets_tenant_plate ON assets (tenant_id, plate) WHERE deleted_at IS NULL');
    }

    public function down(): void
    {
        DB::statement('DROP INDEX IF EXISTS idx_assets_tenant_plate');

        Schema::table('assets', function (Blueprint $table) {
            $table->dropColumn(['plate', 'brand', 'model', 'year']);
        });
    }
};
