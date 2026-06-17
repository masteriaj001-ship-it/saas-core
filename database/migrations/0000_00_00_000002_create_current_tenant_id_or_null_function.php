<?php

declare(strict_types=1);

/**
 * WARNING — current_tenant_id_or_null()
 *
 * Esta función NO debe reemplazar current_tenant_id() en las políticas RLS existentes.
 * Es una función paralela para uso exclusivo en contextos donde NULL es un resultado
 * válido (superadmin, jobs globales, seeders). Si alguien la usa en una policy RLS de
 * datos multi-tenant, desactiva el aislamiento silenciosamente — la policy retornaría
 * NULL y permitiría acceso irrestricto.
 *
 * Único propósito: superadmin que opera SIN contexto de tenant (panel /superadmin,
 * comandos globales) no debe explotar por falta de app.current_tenant_id.
 */

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        DB::unprepared('
            CREATE OR REPLACE FUNCTION public.current_tenant_id_or_null()
            RETURNS UUID
            LANGUAGE plpgsql
            STABLE
            AS $$
            DECLARE
                v_tenant_id TEXT;
            BEGIN
                v_tenant_id := current_setting(\'app.current_tenant_id\', true);

                IF v_tenant_id IS NULL OR v_tenant_id = \'\' THEN
                    RETURN NULL;
                END IF;

                RETURN v_tenant_id::UUID;
            EXCEPTION
                WHEN OTHERS THEN
                    RETURN NULL;
            END;
            $$;
        ');
    }

    public function down(): void
    {
        DB::unprepared('DROP FUNCTION IF EXISTS public.current_tenant_id_or_null()');
    }
};
