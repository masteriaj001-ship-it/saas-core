<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        DB::unprepared('
            CREATE OR REPLACE FUNCTION public.current_tenant_id()
            RETURNS UUID
            LANGUAGE plpgsql
            STABLE
            AS $$
            DECLARE
                v_tenant_id TEXT;
            BEGIN
                v_tenant_id := current_setting(\'app.current_tenant_id\', true);

                IF v_tenant_id IS NULL OR v_tenant_id = \'\' THEN
                    RAISE EXCEPTION \'tenant_context_missing: No tenant ID in session\'
                        USING ERRCODE = \'P0001\';
                END IF;

                IF v_tenant_id !~ \'^[0-9a-f]{8}-[0-9a-f]{4}-[0-9a-f]{4}-[0-9a-f]{4}-[0-9a-f]{12}$\' THEN
                    RAISE EXCEPTION \'tenant_context_invalid: Malformed UUID\'
                        USING ERRCODE = \'P0002\';
                END IF;

                RETURN v_tenant_id::UUID;
            END;
            $$;
        ');
    }

    public function down(): void
    {
        DB::unprepared('DROP FUNCTION IF EXISTS public.current_tenant_id()');
    }
};
