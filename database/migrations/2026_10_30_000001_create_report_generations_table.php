<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('report_generations', function (Blueprint $table) {
            $table->id();
            $table->foreignId('generated_by')->constrained('users')->restrictOnDelete();
            $table->string('report_type', 10);
            $table->string('format', 10);
            $table->json('record_ids');
            $table->json('snapshot');
            $table->ipAddress('ip_address')->nullable();
            $table->timestamps();
            $table->index(['generated_by', 'created_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('report_generations');
    }
};
