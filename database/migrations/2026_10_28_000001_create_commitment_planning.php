<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('evaluation_cycles', function (Blueprint $t) {
            $t->boolean('planning_enabled')->default(false);
            $t->text('planning_readiness')->nullable();
        });
        Schema::table('offices', function (Blueprint $t) {
            $t->foreignId('parent_office_id')->nullable()->constrained('offices')->restrictOnDelete();
            $t->string('planning_level')->nullable();
            $t->text('mandate')->nullable();
            $t->foreignId('head_id')->nullable()->constrained('users')->restrictOnDelete();
            $t->foreignId('planning_reviewer_id')->nullable()->constrained('users')->restrictOnDelete();
            $t->timestamp('hierarchy_verified_at')->nullable();
        });
        Schema::table('users', fn (Blueprint $t) => $t->text('planning_duties')->nullable());
        foreach (['dpcrs', 'upcrs'] as $table) {
            Schema::table($table, fn (Blueprint $t) => $t->foreignId('office_id')->nullable()->constrained()->restrictOnDelete());
        }
        Schema::create('commitment_plans', function (Blueprint $t) {
            $t->id();
            $t->morphs('report');
            $t->foreignId('evaluation_cycle_id')->constrained()->restrictOnDelete();
            $t->foreignId('office_id')->constrained()->restrictOnDelete();
            $t->foreignId('employee_id')->nullable()->constrained('users')->restrictOnDelete();
            $t->foreignId('parent_id')->nullable()->constrained('commitment_plans')->restrictOnDelete();
            $t->foreignId('prepared_by')->constrained('users')->restrictOnDelete();
            $t->foreignId('reviewer_id')->constrained('users')->restrictOnDelete();
            $t->string('level');
            $t->string('state')->default('draft');
            $t->unsignedInteger('revision')->default(1);
            $t->unsignedInteger('approved_revision')->nullable();
            $t->text('head_exception_reason')->nullable();
            $t->timestamp('agreed_at')->nullable();
            $t->timestamp('approved_at')->nullable();
            $t->timestamps();
            $t->unique(['report_type', 'report_id']);
            $t->unique(['evaluation_cycle_id', 'office_id', 'level', 'employee_id'], 'commitment_owner_unique');
        });
        Schema::table('performance_targets', function (Blueprint $t) {
            $t->foreignId('commitment_plan_id')->nullable()->constrained()->restrictOnDelete();
            $t->foreignId('parent_target_id')->nullable()->constrained('performance_targets')->restrictOnDelete();
            $t->foreignId('root_target_id')->nullable()->constrained('performance_targets')->restrictOnDelete();
            $t->unsignedInteger('source_revision')->nullable();
            $t->unsignedInteger('plan_revision')->nullable();
            $t->string('measurement')->nullable();
            $t->decimal('quantity', 14, 2)->nullable();
            $t->string('unit', 100)->nullable();
            $t->boolean('whole_numbers')->default(true);
            $t->text('standards')->nullable();
            $t->text('source_evidence')->nullable();
            $t->json('allocation_basis')->nullable();
            $t->boolean('needs_reconciliation')->default(false);
            $t->boolean('contributes_only')->default(false);
            $t->foreignId('supersedes_id')->nullable()->constrained('performance_targets')->restrictOnDelete();
        });
        Schema::table('office_targets', fn (Blueprint $t) => $t->foreignId('performance_target_id')->nullable()->unique()->constrained()->restrictOnDelete());
        Schema::table('target_document_drafts', fn (Blueprint $t) => $t->foreignId('performance_target_id')->nullable()->constrained()->restrictOnDelete());
        Schema::create('target_eligibilities', function (Blueprint $t) {
            $t->id();
            $t->foreignId('performance_target_id')->constrained()->restrictOnDelete();
            $t->foreignId('office_id')->constrained()->restrictOnDelete();
            $t->foreignId('employee_id')->nullable()->constrained('users')->restrictOnDelete();
            $t->text('reason');
            $t->foreignId('confirmed_by')->constrained('users')->restrictOnDelete();
            $t->timestamps();
            $t->unique(['performance_target_id', 'office_id', 'employee_id'], 'target_recipient_eligibility_unique');
        });
        Schema::create('planning_drivers', function (Blueprint $t) {
            $t->id();
            $t->foreignId('performance_target_id')->constrained()->restrictOnDelete();
            $t->foreignId('office_id')->constrained()->restrictOnDelete();
            $t->foreignId('employee_id')->nullable()->constrained('users')->restrictOnDelete();
            $t->decimal('value', 14, 2);
            $t->decimal('capacity', 14, 2)->nullable();
            $t->string('name');
            $t->text('source');
            $t->date('valid_until');
            $t->foreignId('confirmed_by')->constrained('users')->restrictOnDelete();
            $t->timestamps();
            $t->unique(['performance_target_id', 'office_id', 'employee_id'], 'target_recipient_driver_unique');
        });
        Schema::create('planning_outputs', function (Blueprint $t) {
            $t->id();
            $t->foreignId('performance_target_id')->constrained()->restrictOnDelete();
            $t->foreignId('root_target_id')->constrained('performance_targets')->restrictOnDelete();
            $t->string('reference');
            $t->text('details');
            $t->string('submission_mode')->default('incremental');
            $t->decimal('quantity', 14, 2)->default(0);
            $t->decimal('numerator', 14, 2)->nullable();
            $t->decimal('denominator', 14, 2)->nullable();
            $t->json('evidence');
            $t->foreignId('submitted_by')->constrained('users')->restrictOnDelete();
            $t->foreignId('validated_by')->nullable()->constrained('users')->restrictOnDelete();
            $t->timestamp('validated_at')->nullable();
            $t->timestamps();
            $t->unique(['root_target_id', 'reference']);
        });
        Schema::create('planning_contributions', function (Blueprint $t) {
            $t->id();
            $t->foreignId('planning_output_id')->constrained()->restrictOnDelete();
            $t->foreignId('performance_target_id')->constrained()->restrictOnDelete();
            $t->text('details');
            $t->timestamps();
            $t->unique(['planning_output_id', 'performance_target_id']);
        });
        Schema::create('target_proposals', function (Blueprint $t) {
            $t->id();
            $t->foreignId('evaluation_cycle_id')->constrained()->restrictOnDelete();
            $t->foreignId('office_id')->constrained()->restrictOnDelete();
            $t->foreignId('submitted_by')->constrained('users')->restrictOnDelete();
            $t->foreignId('review_office_id')->constrained('offices')->restrictOnDelete();
            $t->foreignId('root_plan_id')->nullable()->constrained('commitment_plans')->restrictOnDelete();
            $t->foreignId('result_target_id')->nullable()->constrained('performance_targets')->restrictOnDelete();
            $t->foreignId('amends_target_id')->nullable()->constrained('performance_targets')->restrictOnDelete();
            $t->json('proposal');
            $t->string('state')->default('pending');
            $t->text('review_notes')->nullable();
            $t->timestamps();
        });
        Schema::create('planning_events', function (Blueprint $t) {
            $t->id();
            $t->foreignId('commitment_plan_id')->nullable()->constrained()->restrictOnDelete();
            $t->foreignId('actor_id')->constrained('users')->restrictOnDelete();
            $t->string('action');
            $t->json('data');
            $t->timestamps();
        });
    }

    public function down(): void
    {
        foreach (['planning_events', 'target_proposals', 'planning_contributions', 'planning_outputs', 'planning_drivers', 'target_eligibilities'] as $table) {
            Schema::dropIfExists($table);
        }
        Schema::table('office_targets', function (Blueprint $t) {
            $t->dropUnique(['performance_target_id']);
            $t->dropConstrainedForeignId('performance_target_id');
        });
        Schema::table('target_document_drafts', fn (Blueprint $t) => $t->dropConstrainedForeignId('performance_target_id'));
        Schema::table('performance_targets', function (Blueprint $t) {
            foreach (['commitment_plan_id', 'parent_target_id', 'root_target_id', 'supersedes_id'] as $column) {
                $t->dropConstrainedForeignId($column);
            }
            $t->dropColumn(['source_revision', 'plan_revision', 'measurement', 'quantity', 'unit', 'whole_numbers', 'standards', 'source_evidence', 'allocation_basis', 'needs_reconciliation', 'contributes_only']);
        });
        Schema::dropIfExists('commitment_plans');
        foreach (['dpcrs', 'upcrs'] as $table) {
            Schema::table($table, fn (Blueprint $t) => $t->dropConstrainedForeignId('office_id'));
        }
        Schema::table('users', fn (Blueprint $t) => $t->dropColumn('planning_duties'));
        Schema::table('offices', function (Blueprint $t) {
            foreach (['parent_office_id', 'head_id', 'planning_reviewer_id'] as $column) {
                $t->dropConstrainedForeignId($column);
            }
            $t->dropColumn(['planning_level', 'mandate', 'hierarchy_verified_at']);
        });
        Schema::table('evaluation_cycles', fn (Blueprint $t) => $t->dropColumn(['planning_enabled', 'planning_readiness']));
    }
};
