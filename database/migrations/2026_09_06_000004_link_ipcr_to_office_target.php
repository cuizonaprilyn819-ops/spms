<?php
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
return new class extends Migration {
    public function up(): void {
        Schema::table('ipcrs', function (Blueprint $table) {
            $table->unsignedBigInteger('upcr_id')->nullable()->change();
            $table->foreignId('office_target_id')->nullable()->constrained()->restrictOnDelete();
            $table->unique(['office_target_id', 'employee_id']);
        });
    }
    public function down(): void {
        Schema::table('ipcrs', function (Blueprint $table) {
            $table->dropUnique(['office_target_id', 'employee_id']);
            $table->dropConstrainedForeignId('office_target_id');
            $table->unsignedBigInteger('upcr_id')->nullable(false)->change();
        });
    }
};
