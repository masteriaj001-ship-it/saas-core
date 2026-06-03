<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('assets', function (Blueprint $table) {
            $table->string('vin', 100)->nullable()->after('year');
            $table->foreignUuid('owner_id')->nullable()->after('vin')->constrained('contacts')->nullOnDelete();
        });

        DB::statement('CREATE UNIQUE INDEX IF NOT EXISTS idx_assets_tenant_vin ON assets (tenant_id, vin) WHERE deleted_at IS NULL');
        DB::statement('CREATE INDEX IF NOT EXISTS idx_assets_owner ON assets (tenant_id, owner_id)');
    }

    public function down(): void
    {
        DB::statement('DROP INDEX IF EXISTS idx_assets_tenant_vin');
        DB::statement('DROP INDEX IF EXISTS idx_assets_owner');

        Schema::table('assets', function (Blueprint $table) {
            $table->dropConstrainedForeignId('owner_id');
            $table->dropColumn('vin');
        });
    }
};
