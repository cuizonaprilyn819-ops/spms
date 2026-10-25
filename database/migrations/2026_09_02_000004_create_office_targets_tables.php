<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::create('office_targets', function (Blueprint $table) {
            $table->id();
            $table->foreignId('evaluation_cycle_id')->nullable()->constrained()->nullOnDelete();
            $table->string('title');
            $table->text('description')->nullable();
            $table->text('success_indicator');
            $table->decimal('target_value', 12, 2);
            $table->string('unit_of_measure', 100);
            $table->decimal('weight', 5, 2);
            $table->date('start_date')->nullable();
            $table->date('deadline');
            $table->string('priority')->default('medium');
            $table->text('instructions')->nullable();
            $table->string('file_path')->nullable();
            $table->string('original_name')->nullable();
            $table->foreignId('created_by')->constrained('users')->cascadeOnDelete();
            $table->timestamps();
        });

        Schema::create('office_target_office', function (Blueprint $table) {
            $table->id();
            $table->foreignId('office_target_id')->constrained()->cascadeOnDelete();
            $table->foreignId('office_id')->constrained()->cascadeOnDelete();
            $table->timestamps();
            $table->unique(['office_target_id', 'office_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('office_target_office');
        Schema::dropIfExists('office_targets');
    }
};
