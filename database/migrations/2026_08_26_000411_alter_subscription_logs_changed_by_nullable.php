<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('subscription_logs', function (Blueprint $table): void {
            $table->uuid('changed_by')->nullable()->change();
        });
    }

    public function down(): void
    {
        Schema::table('subscription_logs', function (Blueprint $table): void {
            $table->uuid('changed_by')->nullable(false)->change();
        });
    }
};
