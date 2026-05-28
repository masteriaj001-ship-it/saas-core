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
        Schema::create('users', function (Blueprint $table) {
            $table->uuid('id')->primary()->default(DB::raw('gen_random_uuid()'));
            $table->foreignUuid('tenant_id')->constrained('tenants')->cascadeOnDelete();
            $table->string('name', 255);
            $table->string('email', 255)->unique();
            $table->timestamp('email_verified_at')->nullable();
            $table->string('password');
            $table->rememberToken();
            $table->softDeletes();
            $table->timestamps();

            $table->index('tenant_id');
            $table->index(['tenant_id', 'created_at']);
        });

        DB::unprepared('
            ALTER TABLE users ENABLE ROW LEVEL SECURITY;
            ALTER TABLE users FORCE ROW LEVEL SECURITY;

            CREATE POLICY "users_select"
                ON users FOR SELECT
                USING (
                    current_setting(\'app.current_tenant_id\', true) IS NULL
                    OR current_setting(\'app.current_tenant_id\', true) = \'\'
                    OR tenant_id = current_tenant_id()
                );

            CREATE POLICY "users_insert"
                ON users FOR INSERT
                WITH CHECK (tenant_id = current_tenant_id());

            CREATE POLICY "users_update"
                ON users FOR UPDATE
                USING (tenant_id = current_tenant_id())
                WITH CHECK (tenant_id = current_tenant_id());

            CREATE POLICY "users_delete"
                ON users FOR DELETE
                USING (tenant_id = current_tenant_id());
        ');

        Schema::create('password_reset_tokens', function (Blueprint $table) {
            $table->string('email')->primary();
            $table->string('token');
            $table->timestamp('created_at')->nullable();
        });

        Schema::create('sessions', function (Blueprint $table) {
            $table->string('id')->primary();
            $table->foreignUuid('user_id')->nullable()->index();
            $table->string('ip_address', 45)->nullable();
            $table->text('user_agent')->nullable();
            $table->longText('payload');
            $table->integer('last_activity')->index();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('sessions');
        Schema::dropIfExists('password_reset_tokens');

        DB::unprepared('
            DROP POLICY IF EXISTS "users_delete" ON users;
            DROP POLICY IF EXISTS "users_update" ON users;
            DROP POLICY IF EXISTS "users_insert" ON users;
            DROP POLICY IF EXISTS "users_select" ON users;
            ALTER TABLE users FORCE ROW LEVEL SECURITY;
            ALTER TABLE users DISABLE ROW LEVEL SECURITY;
        ');

        Schema::dropIfExists('users');
    }
};
