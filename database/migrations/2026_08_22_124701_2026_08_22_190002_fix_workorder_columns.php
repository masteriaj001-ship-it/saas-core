<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::table('work_orders', function (Blueprint $table) {
            // Rename old columns to new names
            if (Schema::hasColumn('work_orders', 'service_description')) {
                $table->renameColumn('service_description', 'client_report');
            }
            if (Schema::hasColumn('work_orders', 'description')) {
                $table->renameColumn('description', 'internal_notes');
            }

            // Drop reception_notes
            if (Schema::hasColumn('work_orders', 'reception_notes')) {
                $table->dropColumn('reception_notes');
            }

            // Add new timestamp columns
            if (! Schema::hasColumn('work_orders', 'estimated_completion_at')) {
                $table->datetime('estimated_completion_at')->nullable()->after('mileage_km');
            }
            if (! Schema::hasColumn('work_orders', 'actual_started_at')) {
                $table->datetime('actual_started_at')->nullable()->after('estimated_completion_at');
            }
            if (! Schema::hasColumn('work_orders', 'actual_completed_at')) {
                $table->datetime('actual_completed_at')->nullable()->after('actual_started_at');
            }

            // Add location_id if missing (spec says "Sin uso, multi-sede es roadmap")
            if (! Schema::hasColumn('work_orders', 'location_id')) {
                $table->unsignedBigInteger('location_id')->nullable()->after('advisor_id');
            }
        });
    }

    public function down(): void
    {
        Schema::table('work_orders', function (Blueprint $table) {
            // Reverse renames
            if (Schema::hasColumn('work_orders', 'client_report')) {
                $table->renameColumn('client_report', 'service_description');
            }
            if (Schema::hasColumn('work_orders', 'internal_notes')) {
                $table->renameColumn('internal_notes', 'description');
            }

            // Reverse new columns
            if (Schema::hasColumn('work_orders', 'estimated_completion_at')) {
                $table->dropColumn('estimated_completion_at');
            }
            if (Schema::hasColumn('work_orders', 'actual_started_at')) {
                $table->dropColumn('actual_started_at');
            }
            if (Schema::hasColumn('work_orders', 'actual_completed_at')) {
                $table->dropColumn('actual_completed_at');
            }

            // Reverse location_id
            if (Schema::hasColumn('work_orders', 'location_id')) {
                $table->dropColumn('location_id');
            }

            // Reverse reception_notes
            if (! Schema::hasColumn('work_orders', 'reception_notes')) {
                $table->text('reception_notes')->nullable()->after('advisor_id');
            }
        });
    }
};
