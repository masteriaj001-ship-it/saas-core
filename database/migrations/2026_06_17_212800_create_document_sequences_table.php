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
        Schema::create('document_sequences', function (Blueprint $table): void {
            $table->uuid('id')->primary()->default(DB::raw('gen_random_uuid()'));

            $table->foreignUuid('tenant_id')
                ->constrained('tenants')
                ->cascadeOnDelete();

            $table->string('type', 20);
            $table->string('prefix', 10);
            $table->integer('last_sequence')->default(0);

            $table->timestampTz('created_at')->default(DB::raw('CURRENT_TIMESTAMP'));
            $table->timestampTz('updated_at')->default(DB::raw('CURRENT_TIMESTAMP'));

            $table->unique(['tenant_id', 'type'], 'uq_docseq_tenant_type');
            $table->index('tenant_id', 'idx_docseq_tenant');
        });

        DB::statement('ALTER TABLE document_sequences ENABLE ROW LEVEL SECURITY');
        DB::statement('ALTER TABLE document_sequences FORCE ROW LEVEL SECURITY');

        DB::statement(<<<'SQL'
            CREATE POLICY tenant_isolation_select ON document_sequences
                FOR SELECT USING (tenant_id = public.current_tenant_id())
        SQL);

        DB::statement(<<<'SQL'
            CREATE POLICY tenant_isolation_insert ON document_sequences
                FOR INSERT WITH CHECK (tenant_id = public.current_tenant_id())
        SQL);

        DB::statement(<<<'SQL'
            CREATE POLICY tenant_isolation_update ON document_sequences
                FOR UPDATE USING (tenant_id = public.current_tenant_id())
        SQL);

        DB::statement(<<<'SQL'
            CREATE POLICY tenant_isolation_delete ON document_sequences
                FOR DELETE USING (tenant_id = public.current_tenant_id())
        SQL);
    }

    public function down(): void
    {
        Schema::dropIfExists('document_sequences');
    }
};
