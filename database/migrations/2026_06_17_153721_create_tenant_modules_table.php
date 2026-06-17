<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('tenant_modules', function (Blueprint $table) {
            $table->timestampTz('expires_at')->nullable()->after('activated_at');
        });
    }

    public function down(): void
    {
        Schema::table('tenant_modules', function (Blueprint $table) {
            $table->dropColumn('expires_at');
        });
    }
};
