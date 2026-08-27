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
        Schema::create('appointments', function (Blueprint $table) {
            $table->uuid('id')->primary()->default(DB::raw('gen_random_uuid()'));
            $table->foreignUuid('tenant_id')->constrained('tenants')->cascadeOnDelete();
            $table->foreignUuid('contact_id')->nullable()->constrained('contacts')->nullOnDelete();
            $table->foreignUuid('client_vehicle_id')->nullable()->constrained('client_vehicles')->nullOnDelete();
            $table->foreignUuid('location_id')->nullable()->constrained('locations')->nullOnDelete();
            $table->foreignUuid('bay_id')->nullable()->constrained('workshop_bays')->nullOnDelete();
            $table->foreignUuid('mechanic_id')->nullable()->constrained('users')->nullOnDelete();
            $table->string('title', 255);
            $table->text('description')->nullable();
            $table->string('status', 50)->default('scheduled');
            $table->timestamp('scheduled_at');
            $table->integer('duration_minutes')->default(60);
            $table->timestamp('started_at')->nullable();
            $table->timestamp('completed_at')->nullable();
            $table->text('notes')->nullable();
            $table->jsonb('metadata')->default('{}');
            $table->foreignUuid('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignUuid('updated_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
            $table->softDeletes();

            $table->index('tenant_id');
            $table->index(['tenant_id', 'scheduled_at']);
            $table->index(['tenant_id', 'contact_id']);
            $table->index(['tenant_id', 'bay_id']);
            $table->index(['tenant_id', 'mechanic_id']);
            $table->index(['tenant_id', 'status']);
        });

        DB::statement('ALTER TABLE appointments ENABLE ROW LEVEL SECURITY');
        DB::statement('ALTER TABLE appointments FORCE ROW LEVEL SECURITY');

        DB::statement(<<<'SQL'
            CREATE POLICY appointments_tenant_isolation ON appointments
                FOR SELECT USING (tenant_id = public.current_tenant_id())
        SQL);
        DB::statement(<<<'SQL'
            CREATE POLICY appointments_insert ON appointments
                FOR INSERT WITH CHECK (tenant_id = public.current_tenant_id())
        SQL);
        DB::statement(<<<'SQL'
            CREATE POLICY appointments_update ON appointments
                FOR UPDATE USING (tenant_id = public.current_tenant_id())
        SQL);
        DB::statement(<<<'SQL'
            CREATE POLICY appointments_delete ON appointments
                FOR DELETE USING (tenant_id = public.current_tenant_id())
        SQL);
    }

    public function down(): void
    {
        DB::statement('DROP POLICY IF EXISTS appointments_delete ON appointments');
        DB::statement('DROP POLICY IF EXISTS appointments_update ON appointments');
        DB::statement('DROP POLICY IF EXISTS appointments_insert ON appointments');
        DB::statement('DROP POLICY IF EXISTS appointments_tenant_isolation ON appointments');
        DB::statement('ALTER TABLE appointments FORCE ROW LEVEL SECURITY');
        DB::statement('ALTER TABLE appointments DISABLE ROW LEVEL SECURITY');

        Schema::dropIfExists('appointments');
    }
};
