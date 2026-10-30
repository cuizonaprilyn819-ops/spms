<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('users', fn (Blueprint $table) => $table->boolean('is_demo')->default(false));
        Schema::create('user_identities', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->restrictOnDelete();
            $table->string('provider', 32);
            $table->string('issuer', 191);
            $table->string('subject', 191);
            $table->string('provider_email')->nullable();
            $table->timestamps();
            $table->unique(['provider', 'issuer', 'subject']);
        });
        Schema::create('historical_pcrs', function (Blueprint $table) {
            $table->id();
            $table->foreignId('evaluation_cycle_id')->constrained()->restrictOnDelete();
            $table->foreignId('owner_id')->constrained('users')->restrictOnDelete();
            $table->foreignId('imported_by')->constrained('users')->restrictOnDelete();
            $table->string('type', 4);
            $table->string('owner_name');
            $table->string('source_name');
            $table->string('source_path');
            $table->char('source_sha256', 64);
            $table->unsignedBigInteger('source_bytes');
            $table->unsignedInteger('source_pages');
            $table->unsignedInteger('form_pages');
            $table->string('image_directory');
            $table->unsignedBigInteger('current_revision_id')->nullable();
            $table->timestamps();
            $table->unique(['owner_id', 'evaluation_cycle_id', 'type', 'source_sha256'], 'historical_pcr_source_unique');
        });
        Schema::create('historical_pcr_revisions', function (Blueprint $table) {
            $table->id();
            $table->foreignId('historical_pcr_id')->constrained()->restrictOnDelete();
            $table->unsignedInteger('number');
            $table->json('snapshot');
            $table->string('reason', 2000);
            $table->foreignId('created_by')->constrained('users')->restrictOnDelete();
            $table->foreignId('verified_by')->nullable()->constrained('users')->restrictOnDelete();
            $table->timestamp('verified_at')->nullable();
            $table->timestamps();
            $table->unique(['historical_pcr_id', 'number']);
        });
        Schema::create('historical_pcr_participants', function (Blueprint $table) {
            $table->id();
            $table->foreignId('historical_pcr_id')->constrained()->restrictOnDelete();
            $table->foreignId('user_id')->constrained()->restrictOnDelete();
            $table->string('source_name');
            $table->string('source_role');
            $table->timestamps();
            $table->unique(['historical_pcr_id', 'user_id']);
        });
        Schema::create('historical_pcr_corrections', function (Blueprint $table) {
            $table->id();
            $table->foreignId('historical_pcr_id')->constrained()->restrictOnDelete();
            $table->foreignId('revision_id')->constrained('historical_pcr_revisions')->restrictOnDelete();
            $table->foreignId('requested_by')->constrained('users')->restrictOnDelete();
            $table->text('message');
            $table->foreignId('resolved_revision_id')->nullable()->constrained('historical_pcr_revisions')->restrictOnDelete();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('historical_pcr_corrections');
        Schema::dropIfExists('historical_pcr_participants');
        Schema::dropIfExists('historical_pcr_revisions');
        Schema::dropIfExists('historical_pcrs');
        Schema::dropIfExists('user_identities');
        Schema::table('users', fn (Blueprint $table) => $table->dropColumn('is_demo'));
    }
};
