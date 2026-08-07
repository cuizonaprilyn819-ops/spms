<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create('upcrs', function (Blueprint $table) {
            $table->id();
            $table->foreignId('dpcr_id')->constrained()->cascadeOnDelete();
            $table->foreignId('employee_id')->constrained('users')->cascadeOnDelete();
            $table->string('title');
            $table->text('success_indicators')->nullable();
            $table->text('targets')->nullable();
            $table->foreignId('prepared_by')->constrained('users')->cascadeOnDelete();
            $table->timestamps();

            $table->unique(['dpcr_id', 'employee_id']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('upcrs');
    }
};
