<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('invoices', function (Blueprint $table): void {
            $table->integer('pos_sequence')->nullable()->after('sequence');
            $table->text('cufe')->nullable()->after('pos_sequence');
            $table->text('qr_code_url')->nullable()->after('cufe');
        });
    }

    public function down(): void
    {
        Schema::table('invoices', function (Blueprint $table): void {
            $table->dropColumn(['pos_sequence', 'cufe', 'qr_code_url']);
        });
    }
};
