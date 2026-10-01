<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    /**
     * The original users_select policy used:
     *
     *   current_setting(...) IS NULL OR current_setting(...) = '' OR tenant_id = current_tenant_id()
     *
     * PostgreSQL does not guarantee left-to-right evaluation of OR, so the
     * planner could evaluate current_tenant_id() even when the first branches
     * were already true, raising "tenant_context_missing" for any SELECT on
     * users without a tenant context (login, password reset, registration
     * unique-email validation).
     *
     * CASE guarantees ordered evaluation: the raising branch only runs when a
     * context exists. Semantics are unchanged:
     *   - no context      -> all rows visible (required by login/reset flows)
     *   - context set     -> tenant isolation enforced
     *   - malformed UUID  -> exception (same as before)
     */
    public function up(): void
    {
        DB::unprepared('
            DROP POLICY IF EXISTS "users_select" ON users;

            CREATE POLICY "users_select"
                ON users FOR SELECT
                USING (
                    CASE
                        WHEN current_setting(\'app.current_tenant_id\', true) IS NULL
                          OR current_setting(\'app.current_tenant_id\', true) = \'\'
                        THEN true
                        ELSE tenant_id = current_tenant_id()
                    END
                );

            DROP FUNCTION IF EXISTS public.email_exists(text);
        ');
    }

    public function down(): void
    {
        DB::unprepared('
            DROP POLICY IF EXISTS "users_select" ON users;

            CREATE POLICY "users_select"
                ON users FOR SELECT
                USING (
                    current_setting(\'app.current_tenant_id\', true) IS NULL
                    OR current_setting(\'app.current_tenant_id\', true) = \'\'
                    OR tenant_id = current_tenant_id()
                );
        ');
    }
};
