<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        DB::statement('
            CREATE OR REPLACE FUNCTION public.email_exists(check_email text)
            RETURNS boolean
            LANGUAGE sql
            SECURITY DEFINER
            SET search_path = public
            AS $$
                SELECT EXISTS(
                    SELECT 1 FROM users
                    WHERE email = check_email
                    AND deleted_at IS NULL
                );
            $$
        ');
    }

    public function down(): void
    {
        DB::statement('DROP FUNCTION IF EXISTS public.email_exists(text)');
    }
};
