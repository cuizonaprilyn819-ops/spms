<?php
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\{Schema, DB};
return new class extends Migration {
    public function up(): void {
        Schema::table('office_target_employee', fn (Blueprint $table) => $table->timestamp('accepted_at')->nullable());
        // Existing work already in progress remains accepted.
        DB::table('office_target_employee')->where(function ($q) {
            $q->whereNotNull('completed_at')->orWhereExists(function ($mov) {
                $mov->selectRaw('1')->from('target_movs')->whereColumn('target_movs.office_target_id', 'office_target_employee.office_target_id')->whereColumn('target_movs.user_id', 'office_target_employee.user_id');
            });
        })->update(['accepted_at' => now()]);
    }
    public function down(): void { Schema::table('office_target_employee', fn (Blueprint $table) => $table->dropColumn('accepted_at')); }
};
