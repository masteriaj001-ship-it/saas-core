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
        Schema::table('contacts', function (Blueprint $table): void {
            $table->string('document_type', 10)->nullable()->after('contact_type');
            $table->string('document_number', 30)->nullable()->after('document_type');
            $table->string('city', 100)->nullable()->after('address');
        });

        DB::statement('CREATE INDEX IF NOT EXISTS idx_contacts_document ON contacts (tenant_id, document_number) WHERE document_number IS NOT NULL AND deleted_at IS NULL');
    }

    public function down(): void
    {
        DB::statement('DROP INDEX IF EXISTS idx_contacts_document');

        Schema::table('contacts', function (Blueprint $table): void {
            $table->dropColumn(['document_type', 'document_number', 'city']);
        });
    }
};
