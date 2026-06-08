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
        Schema::create('contact_roles', function (Blueprint $table): void {
            $table->uuid('id')->primary()->default(DB::raw('gen_random_uuid()'));

            $table->foreignUuid('tenant_id')
                ->constrained('tenants')
                ->cascadeOnDelete();

            $table->foreignUuid('contact_id')
                ->constrained('contacts')
                ->cascadeOnDelete();

            $table->string('role_code', 50);
            $table->jsonb('metadata')->default(DB::raw("'{}'::jsonb"));

            $table->timestampTz('created_at')->default(DB::raw('CURRENT_TIMESTAMP'));
            $table->timestampTz('updated_at')->default(DB::raw('CURRENT_TIMESTAMP'));

            $table->unique(['contact_id', 'role_code']);
            $table->index(['tenant_id', 'contact_id'], 'idx_cr_tenant_contact');
            $table->index(['tenant_id', 'role_code'], 'idx_cr_tenant_role');
        });

        DB::statement('ALTER TABLE contact_roles ENABLE ROW LEVEL SECURITY');
        DB::statement('ALTER TABLE contact_roles FORCE ROW LEVEL SECURITY');

        DB::statement(
            'CREATE POLICY tenant_isolation_select ON contact_roles '
            .'FOR SELECT USING (tenant_id = current_tenant_id())'
        );

        DB::statement(
            'CREATE POLICY tenant_isolation_insert ON contact_roles '
            .'FOR INSERT WITH CHECK (tenant_id = current_tenant_id())'
        );

        DB::statement(
            'CREATE POLICY tenant_isolation_update ON contact_roles '
            .'FOR UPDATE USING (tenant_id = current_tenant_id())'
        );

        DB::statement(
            'CREATE POLICY tenant_isolation_delete ON contact_roles '
            .'FOR DELETE USING (tenant_id = current_tenant_id())'
        );

        // Fase 2 — Data Migration: transform mechanic/advisor contact_type to roles
        DB::statement(
            "INSERT INTO contact_roles (id, tenant_id, contact_id, role_code, metadata)
            SELECT gen_random_uuid(), tenant_id, id, 'mechanic',
                ('{\"migrated_from\": \"contact_type\", \"migrated_at\": \"' || NOW() || '\"}')::jsonb
            FROM contacts
            WHERE contact_type = 'mechanic'"
        );

        DB::statement(
            "UPDATE contacts SET contact_type = 'employee'
            WHERE contact_type = 'mechanic'"
        );

        DB::statement(
            "INSERT INTO contact_roles (id, tenant_id, contact_id, role_code, metadata)
            SELECT gen_random_uuid(), tenant_id, id, 'service_advisor',
                ('{\"migrated_from\": \"contact_type\", \"migrated_at\": \"' || NOW() || '\"}')::jsonb
            FROM contacts
            WHERE contact_type = 'advisor'"
        );

        DB::statement(
            "UPDATE contacts SET contact_type = 'employee'
            WHERE contact_type = 'advisor'"
        );
    }

    public function down(): void
    {
        // Rollback data migration: revert employee back to mechanic/advisor roles
        DB::statement(
            "UPDATE contacts SET contact_type = 'mechanic'
            WHERE id IN (
                SELECT contact_id FROM contact_roles WHERE role_code = 'mechanic'
            )"
        );

        DB::statement(
            "UPDATE contacts SET contact_type = 'advisor'
            WHERE id IN (
                SELECT contact_id FROM contact_roles WHERE role_code = 'service_advisor'
            )"
        );

        Schema::dropIfExists('contact_roles');
    }
};
