<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('campuses', function (Blueprint $t) {
            $t->id();
            $t->string('name')->unique();
            $t->string('code', 50)->unique();
            $t->unsignedInteger('legacy_reference')->nullable()->unique();
            $t->boolean('is_active')->default(true);
            $t->timestamps();
        });
        Schema::table('offices', function (Blueprint $t) {
            $t->foreignId('campus_id')->nullable()->constrained()->restrictOnDelete();
            $t->string('organization_scope', 20)->default('legacy');
        });
        Schema::create('planning_grants', function (Blueprint $t) {
            $t->id();
            $t->foreignId('user_id')->constrained()->restrictOnDelete();
            $t->foreignId('campus_id')->nullable()->constrained()->restrictOnDelete();
            $t->string('scope', 20);
            $t->string('reason', 1000);
            $t->foreignId('granted_by')->constrained('users')->restrictOnDelete();
            $t->timestamps();
            $t->unique(['user_id', 'scope', 'campus_id']);
        });
        Schema::table('evaluation_cycles', function (Blueprint $t) {
            $t->boolean('multi_campus_enabled')->default(false);
            $t->foreignId('university_office_id')->nullable()->constrained('offices')->restrictOnDelete();
        });
        Schema::table('performance_targets', function (Blueprint $t) {
            $t->json('scope_metadata')->nullable();
        });
        Schema::create('target_scope_drafts', function (Blueprint $t) {
            $t->id();
            $t->foreignId('commitment_plan_id')->constrained()->restrictOnDelete();
            $t->unsignedInteger('plan_revision');
            $t->foreignId('created_by')->constrained('users')->restrictOnDelete();
            $t->foreignId('document_draft_id')->nullable()->constrained('target_document_drafts')->restrictOnDelete();
            $t->foreignId('proposal_id')->nullable()->constrained('target_proposals')->restrictOnDelete();
            $t->foreignId('performance_target_id')->nullable()->constrained()->restrictOnDelete();
            $t->json('payload');
            $t->text('ai_advice')->nullable();
            $t->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('target_scope_drafts');
        Schema::table('performance_targets', fn (Blueprint $t) => $t->dropColumn('scope_metadata'));
        Schema::table('evaluation_cycles', function (Blueprint $t) {
            $t->dropConstrainedForeignId('university_office_id');
            $t->dropColumn('multi_campus_enabled');
        });
        Schema::dropIfExists('planning_grants');
        Schema::table('offices', function (Blueprint $t) {
            $t->dropConstrainedForeignId('campus_id');
            $t->dropColumn('organization_scope');
        });
        Schema::dropIfExists('campuses');
    }
};
