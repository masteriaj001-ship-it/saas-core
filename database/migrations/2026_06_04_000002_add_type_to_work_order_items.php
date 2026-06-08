<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('work_order_items', function (Blueprint $table): void {
            $table->string('type', 50)->default('part');
        });
    }

    public function down(): void
    {
        Schema::table('work_order_items', function (Blueprint $table): void {
            $table->dropColumn('type');
        });
    }
};
