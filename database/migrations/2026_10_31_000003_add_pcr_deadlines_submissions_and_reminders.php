<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('evaluation_cycles', function (Blueprint $table) {
            $table->timestamp('pcr_creation_closes_at')->nullable();
            $table->timestamp('pcr_submission_due_at')->nullable();
            $table->unsignedSmallInteger('pcr_reminder_days')->default(7);
        });
        Schema::create('pcr_submissions', function (Blueprint $table) {
            $table->id();
            $table->morphs('report');
            $table->foreignId('evaluation_cycle_id')->constrained()->restrictOnDelete();
            $table->foreignId('employee_id')->constrained('users')->restrictOnDelete();
            $table->string('phase', 20)->default('performance');
            $table->timestamp('due_at');
            $table->timestamp('first_submitted_at');
            $table->timestamps();
            $table->unique(['report_type', 'report_id']);
        });
        Schema::create('pcr_reminder_deliveries', function (Blueprint $table) {
            $table->id();
            $table->foreignId('evaluation_cycle_id')->constrained()->cascadeOnDelete();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->string('report_type', 10);
            $table->unsignedBigInteger('office_id')->default(0);
            $table->date('reminder_date');
            $table->string('channel', 10);
            $table->string('status', 10)->default('pending');
            $table->uuid('claim_token')->nullable();
            $table->timestamp('claimed_at')->nullable();
            $table->timestamp('sent_at')->nullable();
            $table->string('error')->nullable();
            $table->timestamps();
            $table->unique(['evaluation_cycle_id', 'user_id', 'report_type', 'office_id', 'reminder_date', 'channel'], 'pcr_reminder_once_daily');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('pcr_reminder_deliveries');
        Schema::dropIfExists('pcr_submissions');
        Schema::table('evaluation_cycles', fn (Blueprint $table) => $table->dropColumn(['pcr_creation_closes_at', 'pcr_submission_due_at', 'pcr_reminder_days']));
    }
};
