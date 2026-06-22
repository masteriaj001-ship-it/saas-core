<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        DB::statement("UPDATE stock_movements SET movement_type = 'adjustment_out' WHERE movement_type = 'adjustment'");
    }

    public function down(): void
    {
        DB::statement("UPDATE stock_movements SET movement_type = 'adjustment' WHERE movement_type = 'adjustment_out'");
    }
};
