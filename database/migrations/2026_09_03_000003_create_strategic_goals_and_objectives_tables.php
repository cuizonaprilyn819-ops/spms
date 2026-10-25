<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::create('strategic_goals', function (Blueprint $table) {
            $table->id();
            $table->string('title');
            $table->text('description');
            $table->foreignId('created_by')->constrained('users')->cascadeOnDelete();
            $table->timestamps();
        });

        Schema::create('strategic_objectives', function (Blueprint $table) {
            $table->id();
            $table->foreignId('strategic_goal_id')->constrained()->cascadeOnDelete();
            $table->string('title');
            $table->text('description')->nullable();
            $table->timestamps();
        });

        Schema::table('office_targets', function (Blueprint $table) {
            $table->foreignId('strategic_objective_id')->nullable()->after('evaluation_cycle_id')->constrained()->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('office_targets', function (Blueprint $table) {
            $table->dropConstrainedForeignId('strategic_objective_id');
        });
        Schema::dropIfExists('strategic_objectives');
        Schema::dropIfExists('strategic_goals');
    }
};
