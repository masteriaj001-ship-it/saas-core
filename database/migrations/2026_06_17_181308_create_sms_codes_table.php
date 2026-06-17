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
        Schema::create('sms_codes', function (Blueprint $table) {
            $table->uuid('id')->primary()->default(DB::raw('gen_random_uuid()'));
            $table->uuid('tenant_id');
            $table->uuid('work_order_id');
            $table->string('code', 6);
            $table->timestampTz('expires_at');
            $table->integer('send_count')->default(0);
            $table->integer('attempts')->default(0);
            $table->timestampTz('used_at')->nullable();
            $table->timestamps();

            $table->foreign('tenant_id')->references('id')->on('tenants')->cascadeOnDelete();
            $table->foreign('work_order_id')->references('id')->on('work_orders')->cascadeOnDelete();
            $table->index('work_order_id');
            $table->index('tenant_id');
        });

        DB::unprepared('
            ALTER TABLE sms_codes ENABLE ROW LEVEL SECURITY;
            ALTER TABLE sms_codes FORCE ROW LEVEL SECURITY;

            CREATE POLICY sms_codes_tenant_isolation_select ON sms_codes FOR SELECT
                USING (tenant_id = public.current_tenant_id());

            CREATE POLICY sms_codes_tenant_isolation_insert ON sms_codes FOR INSERT
                WITH CHECK (tenant_id = public.current_tenant_id());

            CREATE POLICY sms_codes_tenant_isolation_update ON sms_codes FOR UPDATE
                USING (tenant_id = public.current_tenant_id())
                WITH CHECK (tenant_id = public.current_tenant_id());

            CREATE POLICY sms_codes_tenant_isolation_delete ON sms_codes FOR DELETE
                USING (tenant_id = public.current_tenant_id());
        ');
    }

    public function down(): void
    {
        DB::unprepared('
            DROP POLICY IF EXISTS sms_codes_tenant_isolation_delete ON sms_codes;
            DROP POLICY IF EXISTS sms_codes_tenant_isolation_update ON sms_codes;
            DROP POLICY IF EXISTS sms_codes_tenant_isolation_insert ON sms_codes;
            DROP POLICY IF EXISTS sms_codes_tenant_isolation_select ON sms_codes;
            ALTER TABLE sms_codes FORCE ROW LEVEL SECURITY;
            ALTER TABLE sms_codes DISABLE ROW LEVEL SECURITY;
        ');

        Schema::dropIfExists('sms_codes');
    }
};
