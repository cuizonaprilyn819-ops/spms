<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('evaluations', function (Blueprint $table) {
            $table->id();
            $table->foreignId('employee_id')->constrained('users')->cascadeOnDelete();
            $table->foreignId('performance_target_id')->constrained()->cascadeOnDelete();
            $table->decimal('rating', 4, 2);
            $table->decimal('computed_rating', 6, 2);
            $table->text('remarks')->nullable();
            $table->foreignId('rated_by')->constrained('users')->cascadeOnDelete();
            $table->timestamps();

            $table->unique(['employee_id', 'performance_target_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('evaluations');
    }
};
