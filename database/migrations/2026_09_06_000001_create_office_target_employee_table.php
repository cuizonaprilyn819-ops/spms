<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::create('office_target_employee', function (Blueprint $table) {
            $table->id();
            $table->foreignId('office_target_id')->constrained()->cascadeOnDelete();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->foreignId('assigned_by')->constrained('users');
            $table->timestamps();
            $table->unique(['office_target_id', 'user_id']);
        });
    }
    public function down(): void { Schema::dropIfExists('office_target_employee'); }
};
