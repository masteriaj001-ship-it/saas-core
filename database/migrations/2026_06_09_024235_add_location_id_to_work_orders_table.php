<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('work_orders', function (Blueprint $table) {
            $table->foreignUuid('location_id')->nullable()->after('contact_id')->constrained('locations')->nullOnDelete();
            $table->index(['tenant_id', 'location_id']);
        });
    }

    public function down(): void
    {
        Schema::table('work_orders', function (Blueprint $table) {
            $table->dropForeign(['location_id']);
            $table->dropIndex(['tenant_id', 'location_id']);
            $table->dropColumn('location_id');
        });
    }
};
