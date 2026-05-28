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
        Schema::create('contacts', function (Blueprint $table) {
            $table->uuid('id')->primary()->default(DB::raw('gen_random_uuid()'));
            $table->foreignUuid('tenant_id')->constrained('tenants')->cascadeOnDelete();
            $table->string('contact_type', 50);
            $table->string('name', 255);
            $table->string('tax_id', 50)->nullable();
            $table->string('email', 255)->nullable();
            $table->string('phone', 40)->nullable();
            $table->text('address')->nullable();
            $table->jsonb('metadata')->default('{}');
            $table->softDeletes();
            $table->timestamps();

            $table->index('tenant_id');
            $table->index(['tenant_id', 'contact_type']);
        });

        DB::unprepared('
            ALTER TABLE contacts ENABLE ROW LEVEL SECURITY;
            ALTER TABLE contacts FORCE ROW LEVEL SECURITY;

            CREATE POLICY "contacts_tenant_isolation_select"
                ON contacts FOR SELECT
                USING (tenant_id = public.current_tenant_id());

            CREATE POLICY "contacts_tenant_isolation_insert"
                ON contacts FOR INSERT
                WITH CHECK (tenant_id = public.current_tenant_id());

            CREATE POLICY "contacts_tenant_isolation_update"
                ON contacts FOR UPDATE
                USING (tenant_id = public.current_tenant_id())
                WITH CHECK (tenant_id = public.current_tenant_id());

            CREATE POLICY "contacts_tenant_isolation_delete"
                ON contacts FOR DELETE
                USING (tenant_id = public.current_tenant_id());
        ');
    }

    public function down(): void
    {
        DB::unprepared('
            DROP POLICY IF EXISTS "contacts_tenant_isolation_delete" ON contacts;
            DROP POLICY IF EXISTS "contacts_tenant_isolation_update" ON contacts;
            DROP POLICY IF EXISTS "contacts_tenant_isolation_insert" ON contacts;
            DROP POLICY IF EXISTS "contacts_tenant_isolation_select" ON contacts;
            ALTER TABLE contacts FORCE ROW LEVEL SECURITY;
            ALTER TABLE contacts DISABLE ROW LEVEL SECURITY;
        ');

        Schema::dropIfExists('contacts');
    }
};
