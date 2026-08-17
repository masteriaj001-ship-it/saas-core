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
        Schema::table('work_order_media', function (Blueprint $table) {
            $table->string('stage', 16)->default('after')->after('mime_type');
            $table->index(['tenant_id', 'work_order_id', 'stage'], 'idx_wom_stage');
        });

        DB::statement(
            'ALTER TABLE work_order_media ADD CONSTRAINT work_order_media_stage_check '
            ."CHECK (stage IN ('before', 'after'))"
        );
    }

    public function down(): void
    {
        Schema::table('work_order_media', function (Blueprint $table) {
            $table->dropIndex('idx_wom_stage');
        });

        DB::statement(
            'ALTER TABLE work_order_media DROP CONSTRAINT IF EXISTS work_order_media_stage_check'
        );

        Schema::table('work_order_media', function (Blueprint $table) {
            $table->dropColumn('stage');
        });
    }
};
