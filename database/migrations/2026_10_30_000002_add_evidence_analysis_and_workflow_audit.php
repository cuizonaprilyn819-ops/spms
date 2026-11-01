<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('workflow_audits', function (Blueprint $table) {
            $table->id();
            $table->morphs('subject');
            $table->foreignId('actor_id')->nullable()->constrained('users')->nullOnDelete();
            $table->string('action');
            $table->json('before')->nullable();
            $table->json('after')->nullable();
            $table->timestamp('created_at')->useCurrent();
        });
        Schema::create('evidence_analyses', function (Blueprint $table) {
            $table->id();
            $table->string('evidence_type');
            $table->unsignedBigInteger('evidence_id');
            $table->unique(['evidence_type', 'evidence_id']);
            $table->string('status')->default('pending');
            $table->uuid('analysis_token')->nullable();
            $table->text('summary')->nullable();
            $table->text('statement')->nullable();
            $table->json('findings')->nullable();
            $table->text('error')->nullable();
            $table->timestamps();
        });
    }
    public function down(): void
    {
        Schema::dropIfExists('evidence_analyses');
        Schema::dropIfExists('workflow_audits');
    }
};
