<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('work_orders', function (Blueprint $table): void {
            $table->foreignUuid('mechanic_id')
                ->nullable()
                ->constrained('contacts')
                ->nullOnDelete();

            $table->foreignUuid('advisor_id')
                ->nullable()
                ->constrained('contacts')
                ->nullOnDelete();

            $table->text('reception_notes')->nullable();
            $table->string('fuel_level', 20)->nullable();
            $table->text('diagnosis_summary')->nullable();
            $table->string('approval_channel', 50)->nullable();
            $table->timestampTz('approval_at')->nullable();
            $table->boolean('qc_passed')->nullable();
            $table->text('qc_notes')->nullable();
            $table->timestampTz('delivery_at')->nullable();
        });

        Schema::table('work_orders', function (Blueprint $table): void {
            $table->index(['tenant_id', 'mechanic_id'], 'idx_work_orders_mechanic')
                ->whereRaw('deleted_at IS NULL');

            $table->index(['tenant_id', 'advisor_id'], 'idx_work_orders_advisor')
                ->whereRaw('deleted_at IS NULL');
        });
    }

    public function down(): void
    {
        Schema::table('work_orders', function (Blueprint $table): void {
            $table->dropIndex('idx_work_orders_mechanic');
            $table->dropIndex('idx_work_orders_advisor');
        });

        Schema::table('work_orders', function (Blueprint $table): void {
            $table->dropColumn([
                'mechanic_id',
                'advisor_id',
                'reception_notes',
                'fuel_level',
                'diagnosis_summary',
                'approval_channel',
                'approval_at',
                'qc_passed',
                'qc_notes',
                'delivery_at',
            ]);
        });
    }
};
