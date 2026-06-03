<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('tenants', function (Blueprint $col) {
            $col->boolean('onboarding_completed')->notNull()->default(false);
        });
    }

    public function down(): void
    {
        Schema::table('tenants', function (Blueprint $col) {
            $col->dropColumn('onboarding_completed');
        });
    }
};
