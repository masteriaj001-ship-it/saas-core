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
        Schema::table('assets', function (Blueprint $table) {
            $table->dropIndex('idx_assets_fuel_type');
            $table->dropIndex('idx_assets_mileage');
        });

        DB::statement('ALTER TABLE assets DROP CONSTRAINT IF EXISTS assets_current_mileage_check');
        DB::statement('ALTER TABLE assets DROP CONSTRAINT IF EXISTS assets_year_check');

        Schema::table('assets', function (Blueprint $table) {
            $table->dropColumn([
                'plate', 'brand', 'model', 'version', 'year',
                'vin', 'engine_number', 'color', 'fuel_type',
                'vehicle_type', 'current_mileage', 'owner_contact_id',
            ]);
        });
    }

    public function down(): void
    {
        Schema::table('assets', function (Blueprint $table) {
            $table->string('plate', 20)->nullable()->after('code');
            $table->string('brand', 100)->nullable()->after('plate');
            $table->string('model', 100)->nullable()->after('brand');
            $table->integer('year')->nullable()->after('model');
            $table->string('version', 100)->nullable()->after('year');
            $table->string('vin', 100)->nullable()->after('version');
            $table->string('engine_number', 100)->nullable()->after('vin');
            $table->integer('current_mileage')->nullable()->after('engine_number');
            $table->string('fuel_type', 50)->nullable()->after('current_mileage');
            $table->string('color', 50)->nullable()->after('fuel_type');
            $table->string('vehicle_type', 50)->nullable()->after('color');
            $table->foreignUuid('owner_contact_id')->nullable()->after('vehicle_type')->constrained('contacts')->nullOnDelete();
        });

        DB::statement('ALTER TABLE assets ADD CONSTRAINT assets_current_mileage_check CHECK (current_mileage IS NULL OR current_mileage >= 0)');
        DB::statement('ALTER TABLE assets ADD CONSTRAINT assets_year_check CHECK (year IS NULL OR year >= 1900)');

        Schema::table('assets', function (Blueprint $table) {
            $table->index(['tenant_id', 'fuel_type'], 'idx_assets_fuel_type');
            $table->index(['tenant_id', 'current_mileage'], 'idx_assets_mileage');
        });
    }
};
