<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        $tableNames = config('permission.table_names');
        throw_if(empty($tableNames), 'Error: config/permission.php not loaded.');

        // Permissions
        DB::statement("
            CREATE TABLE {$tableNames['permissions']} (
                id         uuid PRIMARY KEY DEFAULT gen_random_uuid(),
                tenant_id  uuid NOT NULL DEFAULT public.current_tenant_id(),
                name       varchar(255) NOT NULL,
                guard_name varchar(255) NOT NULL DEFAULT 'web',
                created_at timestamptz DEFAULT now(),
                updated_at timestamptz DEFAULT now()
            )
        ");
        DB::statement("ALTER TABLE {$tableNames['permissions']} ADD CONSTRAINT fk_permissions_tenant FOREIGN KEY (tenant_id) REFERENCES tenants(id) ON DELETE CASCADE");
        DB::statement("CREATE UNIQUE INDEX idx_permissions_tenant_name_guard ON {$tableNames['permissions']} (tenant_id, name, guard_name)");
        DB::statement("CREATE INDEX idx_permissions_tenant_id ON {$tableNames['permissions']} (tenant_id)");
        DB::statement("ALTER TABLE {$tableNames['permissions']} ENABLE ROW LEVEL SECURITY");
        DB::statement("ALTER TABLE {$tableNames['permissions']} FORCE ROW LEVEL SECURITY");
        $this->createRlsPolicies($tableNames['permissions']);

        // Roles
        DB::statement("
            CREATE TABLE {$tableNames['roles']} (
                id         uuid PRIMARY KEY DEFAULT gen_random_uuid(),
                tenant_id  uuid NOT NULL DEFAULT public.current_tenant_id(),
                name       varchar(255) NOT NULL,
                guard_name varchar(255) NOT NULL DEFAULT 'web',
                created_at timestamptz DEFAULT now(),
                updated_at timestamptz DEFAULT now()
            )
        ");
        DB::statement("ALTER TABLE {$tableNames['roles']} ADD CONSTRAINT fk_roles_tenant FOREIGN KEY (tenant_id) REFERENCES tenants(id) ON DELETE CASCADE");
        DB::statement("CREATE UNIQUE INDEX idx_roles_tenant_name_guard ON {$tableNames['roles']} (tenant_id, name, guard_name)");
        DB::statement("CREATE INDEX idx_roles_tenant_id ON {$tableNames['roles']} (tenant_id)");
        DB::statement("ALTER TABLE {$tableNames['roles']} ENABLE ROW LEVEL SECURITY");
        DB::statement("ALTER TABLE {$tableNames['roles']} FORCE ROW LEVEL SECURITY");
        $this->createRlsPolicies($tableNames['roles']);

        // model_has_permissions
        DB::statement("
            CREATE TABLE {$tableNames['model_has_permissions']} (
                permission_id uuid NOT NULL,
                model_type    varchar(255) NOT NULL,
                model_id      uuid NOT NULL,
                tenant_id     uuid NOT NULL DEFAULT public.current_tenant_id(),
                PRIMARY KEY (tenant_id, permission_id, model_id, model_type)
            )
        ");
        DB::statement("ALTER TABLE {$tableNames['model_has_permissions']} ADD CONSTRAINT fk_mhp_permission FOREIGN KEY (permission_id) REFERENCES {$tableNames['permissions']}(id) ON DELETE CASCADE");
        DB::statement("ALTER TABLE {$tableNames['model_has_permissions']} ADD CONSTRAINT fk_mhp_tenant FOREIGN KEY (tenant_id) REFERENCES tenants(id) ON DELETE CASCADE");
        DB::statement("CREATE INDEX idx_mhp_tenant_id ON {$tableNames['model_has_permissions']} (tenant_id)");
        DB::statement("CREATE INDEX idx_mhp_model ON {$tableNames['model_has_permissions']} (model_id, model_type)");
        DB::statement("ALTER TABLE {$tableNames['model_has_permissions']} ENABLE ROW LEVEL SECURITY");
        DB::statement("ALTER TABLE {$tableNames['model_has_permissions']} FORCE ROW LEVEL SECURITY");
        $this->createRlsPolicies($tableNames['model_has_permissions']);

        // model_has_roles
        DB::statement("
            CREATE TABLE {$tableNames['model_has_roles']} (
                role_id    uuid NOT NULL,
                model_type varchar(255) NOT NULL,
                model_id   uuid NOT NULL,
                tenant_id  uuid NOT NULL DEFAULT public.current_tenant_id(),
                PRIMARY KEY (tenant_id, role_id, model_id, model_type)
            )
        ");
        DB::statement("ALTER TABLE {$tableNames['model_has_roles']} ADD CONSTRAINT fk_mhr_role FOREIGN KEY (role_id) REFERENCES {$tableNames['roles']}(id) ON DELETE CASCADE");
        DB::statement("ALTER TABLE {$tableNames['model_has_roles']} ADD CONSTRAINT fk_mhr_tenant FOREIGN KEY (tenant_id) REFERENCES tenants(id) ON DELETE CASCADE");
        DB::statement("CREATE INDEX idx_mhr_tenant_id ON {$tableNames['model_has_roles']} (tenant_id)");
        DB::statement("CREATE INDEX idx_mhr_model ON {$tableNames['model_has_roles']} (model_id, model_type)");
        DB::statement("ALTER TABLE {$tableNames['model_has_roles']} ENABLE ROW LEVEL SECURITY");
        DB::statement("ALTER TABLE {$tableNames['model_has_roles']} FORCE ROW LEVEL SECURITY");
        $this->createRlsPolicies($tableNames['model_has_roles']);

        // role_has_permissions
        DB::statement("
            CREATE TABLE {$tableNames['role_has_permissions']} (
                permission_id uuid NOT NULL,
                role_id       uuid NOT NULL,
                tenant_id     uuid NOT NULL DEFAULT public.current_tenant_id(),
                PRIMARY KEY (permission_id, role_id)
            )
        ");
        DB::statement("ALTER TABLE {$tableNames['role_has_permissions']} ADD CONSTRAINT fk_rhp_permission FOREIGN KEY (permission_id) REFERENCES {$tableNames['permissions']}(id) ON DELETE CASCADE");
        DB::statement("ALTER TABLE {$tableNames['role_has_permissions']} ADD CONSTRAINT fk_rhp_role FOREIGN KEY (role_id) REFERENCES {$tableNames['roles']}(id) ON DELETE CASCADE");
        DB::statement("ALTER TABLE {$tableNames['role_has_permissions']} ADD CONSTRAINT fk_rhp_tenant FOREIGN KEY (tenant_id) REFERENCES tenants(id) ON DELETE CASCADE");
        DB::statement("CREATE INDEX idx_rhp_tenant_id ON {$tableNames['role_has_permissions']} (tenant_id)");
        DB::statement("ALTER TABLE {$tableNames['role_has_permissions']} ENABLE ROW LEVEL SECURITY");
        DB::statement("ALTER TABLE {$tableNames['role_has_permissions']} FORCE ROW LEVEL SECURITY");
        $this->createRlsPolicies($tableNames['role_has_permissions']);

        // Flush Spatie cache
        app('cache')->store('array')->forget(config('permission.cache.key'));
    }

    public function down(): void
    {
        $tableNames = config('permission.table_names');
        throw_if(empty($tableNames), 'Error: config/permission.php not found.');

        $tables = [
            $tableNames['role_has_permissions'],
            $tableNames['model_has_roles'],
            $tableNames['model_has_permissions'],
            $tableNames['roles'],
            $tableNames['permissions'],
        ];

        foreach ($tables as $table) {
            DB::statement("DROP POLICY IF EXISTS {$table}_tenant_isolation_select ON {$table}");
            DB::statement("DROP POLICY IF EXISTS {$table}_tenant_isolation_insert ON {$table}");
            DB::statement("DROP POLICY IF EXISTS {$table}_tenant_isolation_update ON {$table}");
            DB::statement("DROP POLICY IF EXISTS {$table}_tenant_isolation_delete ON {$table}");
            DB::statement("ALTER TABLE {$table} FORCE ROW LEVEL SECURITY");
            DB::statement("ALTER TABLE {$table} DISABLE ROW LEVEL SECURITY");
        }

        foreach ($tables as $table) {
            DB::statement("DROP TABLE IF EXISTS {$table} CASCADE");
        }
    }

    private function createRlsPolicies(string $table): void
    {
        DB::statement("
            CREATE POLICY {$table}_tenant_isolation_select
                ON {$table} FOR SELECT
                USING (tenant_id = public.current_tenant_id())
        ");
        DB::statement("
            CREATE POLICY {$table}_tenant_isolation_insert
                ON {$table} FOR INSERT
                WITH CHECK (tenant_id = public.current_tenant_id())
        ");
        DB::statement("
            CREATE POLICY {$table}_tenant_isolation_update
                ON {$table} FOR UPDATE
                USING (tenant_id = public.current_tenant_id())
                WITH CHECK (tenant_id = public.current_tenant_id())
        ");
        DB::statement("
            CREATE POLICY {$table}_tenant_isolation_delete
                ON {$table} FOR DELETE
                USING (tenant_id = public.current_tenant_id())
        ");
    }
};
