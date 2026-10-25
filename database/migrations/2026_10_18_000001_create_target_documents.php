<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('target_documents', function (Blueprint $table) {
            $table->id();
            $table->foreignId('created_by')->constrained('users')->cascadeOnDelete();
            $table->foreignId('evaluation_cycle_id')->constrained()->restrictOnDelete();
            $table->string('original_name');
            $table->string('path');
            $table->string('mime_type');
            $table->string('status')->default('uploaded');
            $table->text('summary')->nullable();
            $table->text('error')->nullable();
            $table->timestamps();
        });
        Schema::create('target_document_drafts', function (Blueprint $table) {
            $table->id();
            $table->foreignId('target_document_id')->constrained()->cascadeOnDelete();
            $table->json('proposal');
            $table->foreignId('office_target_id')->nullable()->unique()->constrained()->restrictOnDelete();
            $table->timestamps();
        });
        Schema::table('office_target_office', function (Blueprint $table) {
            $table->text('ai_briefing')->nullable();
        });
    }

    public function down(): void
    {
        Schema::table('office_target_office', fn (Blueprint $table) => $table->dropColumn('ai_briefing'));
        Schema::dropIfExists('target_document_drafts');
        Schema::dropIfExists('target_documents');
    }
};
