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
            $table->jsonb('settings')->default('{}')->after('status');
            $table->text('signature_hash')->nullable()->after('code');
            $table->timestampTz('signed_at')->nullable()->after('signature_hash');
            $table->text('closure_notes')->nullable()->after('signed_at');
        });

        DB::statement("
            UPDATE work_orders
            SET status = 'work_done',
                settings = jsonb_set(COALESCE(settings, '{}'::jsonb), '{is_legacy_closure}', 'true')
            WHERE status = 'completed'
        ");
    }

    public function down(): void
    {
        DB::statement("
            UPDATE work_orders
            SET status = 'completed',
                settings = settings #- '{is_legacy_closure}'
            WHERE settings->>'is_legacy_closure' = 'true'
        ");

        Schema::table('work_orders', function (Blueprint $table) {
            $table->dropColumn(['settings', 'signature_hash', 'signed_at', 'closure_notes']);
        });
    }
};
