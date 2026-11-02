<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('target_assistances', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->restrictOnDelete();
            $table->string('subject_kind', 40);
            $table->unsignedBigInteger('subject_id')->nullable();
            $table->string('field', 150);
            $table->string('fingerprint', 64);
            $table->string('input_hash', 64);
            $table->json('protected_values');
            $table->text('original');
            $table->text('suggestion')->nullable();
            $table->string('status', 20);
            $table->timestamp('accepted_at')->nullable();
            $table->timestamps();
        });
        Schema::create('target_conflict_scans', function (Blueprint $table) {
            $table->id();
            $table->string('subject_kind', 40);
            $table->unsignedBigInteger('subject_id');
            $table->foreignId('user_id')->constrained()->restrictOnDelete();
            $table->foreignId('evaluation_cycle_id')->nullable()->constrained()->restrictOnDelete();
            $table->unsignedBigInteger('office_id')->nullable()->index();
            $table->string('fingerprint', 64);
            $table->string('status', 20)->default('pending');
            $table->boolean('published')->default(false);
            $table->json('coverage');
            $table->json('snapshot');
            $table->text('failure')->nullable();
            $table->timestamp('finished_at')->nullable();
            $table->timestamps();
            $table->index(['subject_kind', 'subject_id', 'id']);
        });
        Schema::create('target_conflicts', function (Blueprint $table) {
            $table->id();
            $table->foreignId('scan_id')->constrained('target_conflict_scans')->cascadeOnDelete();
            $table->string('source', 12);
            $table->string('code', 60);
            $table->boolean('blocking')->default(false);
            $table->string('status', 24)->default('open');
            $table->string('fingerprint', 64)->index();
            $table->string('title');
            $table->text('explanation');
            $table->text('recommendation');
            $table->json('references');
            $table->timestamps();
            $table->unique(['scan_id', 'fingerprint']);
        });
        Schema::create('target_conflict_dispositions', function (Blueprint $table) {
            $table->id();
            $table->foreignId('scan_id')->constrained('target_conflict_scans')->cascadeOnDelete();
            $table->foreignId('conflict_id')->nullable()->constrained('target_conflicts')->cascadeOnDelete();
            $table->foreignId('user_id')->constrained()->restrictOnDelete();
            $table->string('decision', 32);
            $table->text('reason');
            $table->string('fingerprint', 64);
            $table->timestamps();
        });
        Schema::create('target_conflict_deliveries', function (Blueprint $table) {
            $table->id();
            $table->string('fingerprint', 64);
            $table->foreignId('user_id')->constrained()->restrictOnDelete();
            $table->timestamps();
            $table->unique(['fingerprint', 'user_id']);
        });
    }

    public function down(): void
    {
        foreach (['target_conflict_deliveries', 'target_conflict_dispositions', 'target_conflicts', 'target_conflict_scans', 'target_assistances'] as $table) {
            Schema::dropIfExists($table);
        }
    }
};
