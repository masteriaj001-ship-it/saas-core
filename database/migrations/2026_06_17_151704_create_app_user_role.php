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
        DB::statement('DROP ROLE IF EXISTS app_user');
    }
};
