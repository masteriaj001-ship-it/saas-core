<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        $dbName = config('database.connections.pgsql.database');
        $password = env('APP_USER_PASSWORD', 'secret');

        DB::statement("DO \$\$
        BEGIN
            IF NOT EXISTS (SELECT FROM pg_catalog.pg_roles WHERE rolname = 'app_user') THEN
                CREATE ROLE app_user WITH LOGIN PASSWORD '{$password}' NOBYPASSRLS;
            END IF;
        END
        \$\$;");

        DB::statement("GRANT CONNECT ON DATABASE \"{$dbName}\" TO app_user");
        DB::statement('GRANT USAGE ON SCHEMA public TO app_user');
        DB::statement('GRANT SELECT, INSERT, UPDATE, DELETE ON ALL TABLES IN SCHEMA public TO app_user');
        DB::statement('GRANT USAGE, SELECT ON ALL SEQUENCES IN SCHEMA public TO app_user');
        DB::statement('ALTER DEFAULT PRIVILEGES IN SCHEMA public GRANT SELECT, INSERT, UPDATE, DELETE ON TABLES TO app_user');
        DB::statement('ALTER DEFAULT PRIVILEGES IN SCHEMA public GRANT USAGE, SELECT ON SEQUENCES TO app_user');
    }

    public function down(): void
    {
        try {
            // Drops privileges owned by the role in THIS database only.
            DB::statement('DROP OWNED BY IF EXISTS app_user');
        } catch (Throwable) {
            // El rol no existe o no hay privilegios locales que limpiar.
        }

        try {
            // El rol es cluster-wide: si conserva privilegios en otras bases de
            // datos, DROP ROLE lanzara una dependencia. Lo omitimos para no
            // romper rollbacks/fresh en bases de test; `up()` es idempotente.
            DB::statement('DROP ROLE IF EXISTS app_user');
        } catch (Throwable) {
            // El rol permanece para no romper el teardown; es el comportamiento
            // esperado para roles compartidos entre bases de un mismo cluster.
        }
    }
};
