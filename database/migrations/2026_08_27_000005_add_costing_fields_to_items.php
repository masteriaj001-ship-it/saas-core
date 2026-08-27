<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('items', function (Blueprint $table) {
            $table->decimal('average_cost', 12, 2)->default(0)->after('cost');
            $table->foreignUuid('created_by')->nullable()->constrained('users')->nullOnDelete()->after('metadata');
            $table->foreignUuid('updated_by')->nullable()->constrained('users')->nullOnDelete()->after('created_by');
        });
    }

    public function down(): void
    {
        Schema::table('items', function (Blueprint $table) {
            $table->dropColumn(['average_cost', 'created_by', 'updated_by']);
        });
    }
};
