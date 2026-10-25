<?php
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
return new class extends Migration {
    public function up(): void {
        Schema::create('target_movs', function (Blueprint $table) {
            $table->id();
            $table->foreignId('office_target_id')->constrained()->cascadeOnDelete();
            $table->foreignId('user_id')->constrained();
            $table->foreignId('office_id')->constrained();
            $table->text('accomplishment');
            $table->string('path');
            $table->string('original_name');
            $table->timestamps();
        });
    }
    public function down(): void { Schema::dropIfExists('target_movs'); }
};
