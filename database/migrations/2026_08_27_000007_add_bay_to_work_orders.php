<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('work_orders', function (Blueprint $table) {
            $table->foreignUuid('bay_id')->nullable()->constrained('workshop_bays')->nullOnDelete()->after('location_id');
            $table->foreignUuid('updated_by')->nullable()->constrained('users')->nullOnDelete()->after('metadata');
        });
    }

    public function down(): void
    {
        Schema::table('work_orders', function (Blueprint $table) {
            $table->dropForeign(['bay_id']);
            $table->dropColumn(['bay_id', 'updated_by']);
        });
    }
};
