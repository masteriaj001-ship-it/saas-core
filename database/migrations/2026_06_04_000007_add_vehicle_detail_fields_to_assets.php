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
        Schema::table('assets', function (Blueprint $table): void {
            $table->dropForeign('assets_owner_id_foreign');
            $table->renameColumn('owner_id', 'owner_contact_id');
        });

        Schema::table('assets', function (Blueprint $table): void {
            $table->foreign('owner_contact_id')
                ->references('id')
                ->on('contacts')
                ->nullOnDelete();

            $table->string('version', 100)->nullable()->after('year');
            $table->string('engine_number', 100)->nullable()->after('vin');
            $table->integer('current_mileage')->nullable()->after('engine_number');
            $table->string('fuel_type', 50)->nullable()->after('current_mileage');
            $table->string('color', 50)->nullable()->after('fuel_type');

            $table->index(['tenant_id', 'fuel_type'], 'idx_assets_fuel_type');
            $table->index(['tenant_id', 'current_mileage'], 'idx_assets_mileage');
        });

        // Check constraints
        DB::statement('ALTER TABLE assets ADD CONSTRAINT assets_current_mileage_check CHECK (current_mileage IS NULL OR current_mileage >= 0)');
        DB::statement('ALTER TABLE assets ADD CONSTRAINT assets_year_check CHECK (year IS NULL OR year >= 1900)');

        // Normalize asset_type from legacy 'vehicles' (plural) to 'vehicle' (singular)
        DB::statement("UPDATE assets SET asset_type = 'vehicle' WHERE asset_type = 'vehicles'");
    }

    public function down(): void
    {
        Schema::table('assets', function (Blueprint $table): void {
            $table->dropIndex('idx_assets_fuel_type');
            $table->dropIndex('idx_assets_mileage');

            DB::statement('ALTER TABLE assets DROP CONSTRAINT IF EXISTS assets_current_mileage_check');
            DB::statement('ALTER TABLE assets DROP CONSTRAINT IF EXISTS assets_year_check');

            $table->dropColumn(['version', 'engine_number', 'current_mileage', 'fuel_type', 'color']);

            $table->dropForeign(['owner_contact_id']);
            $table->renameColumn('owner_contact_id', 'owner_id');

            $table->foreign('owner_id')
                ->references('id')
                ->on('contacts')
                ->nullOnDelete();
        });

        // Reverse: convert back (owner_contact_id ya se renombró a owner_id en este punto)
        DB::statement("UPDATE assets SET asset_type = 'vehicles' WHERE asset_type = 'vehicle' AND version IS NULL");
    }
};
