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
            if (Schema::hasColumn('work_orders', 'location_id')) {
                $table->dropColumn('location_id');
            }

            if (! Schema::hasColumn('work_orders', 'inspection_checklist')) {
                $table->jsonb('inspection_checklist')->default('{}')->after('aesthetic_notes');
            }
            if (! Schema::hasColumn('work_orders', 'inspection_completed_at')) {
                $table->timestamp('inspection_completed_at')->nullable()->after('inspection_checklist');
            }
            if (! Schema::hasColumn('work_orders', 'inspection_completed_by')) {
                $table->foreignUuid('inspection_completed_by')->nullable()->after('inspection_completed_at');
            }
        });
    }

    public function down(): void
    {
        Schema::table('work_orders', function (Blueprint $table) {
            if (Schema::hasColumn('work_orders', 'inspection_checklist')) {
                $table->dropColumn('inspection_checklist');
            }
            if (Schema::hasColumn('work_orders', 'inspection_completed_at')) {
                $table->dropColumn('inspection_completed_at');
            }
            if (Schema::hasColumn('work_orders', 'inspection_completed_by')) {
                $table->dropColumn('inspection_completed_by');
            }

            if (! Schema::hasColumn('work_orders', 'location_id')) {
                $table->unsignedBigInteger('location_id')->nullable()->after('advisor_id');
            }
        });
    }
};
