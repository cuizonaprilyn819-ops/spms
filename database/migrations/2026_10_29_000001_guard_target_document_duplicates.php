<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('target_documents', function (Blueprint $table) {
            $table->string('content_hash', 64)->nullable();
            $table->uuid('analysis_token')->nullable();
            $table->unique(['created_by', 'evaluation_cycle_id', 'content_hash'], 'target_document_content_unique');
        });
    }

    public function down(): void
    {
        Schema::table('target_documents', function (Blueprint $table) {
            $table->dropUnique('target_document_content_unique');
            $table->dropColumn(['content_hash', 'analysis_token']);
        });
    }
};
