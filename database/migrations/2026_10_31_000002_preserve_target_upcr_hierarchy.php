<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('office_target_office', function (Blueprint $table) {
            $table->foreignId('upcr_id')->nullable()->constrained('upcrs')->restrictOnDelete();
        });
        // Target IPCRs remain one per target/person; several targets may share an office UPCR.
        Schema::table('ipcrs', function (Blueprint $table) {
            $table->dropUnique(['upcr_id', 'employee_id']);
            $table->index(['upcr_id', 'employee_id']);
        });
        if (in_array(DB::getDriverName(), ['pgsql', 'sqlite'])) {
            DB::statement('CREATE UNIQUE INDEX ipcrs_manual_upcr_employee_unique ON ipcrs (upcr_id, employee_id) WHERE office_target_id IS NULL');
        }
    }

    public function down(): void
    {
        if (DB::table('ipcrs')->select('upcr_id', 'employee_id')->whereNotNull('upcr_id')->groupBy('upcr_id', 'employee_id')->havingRaw('COUNT(*) > 1')->exists()) {
            throw new RuntimeException('Cannot restore the old IPCR uniqueness rule while multiple target IPCRs share a UPCR. No report records have been removed.');
        }
        if (in_array(DB::getDriverName(), ['pgsql', 'sqlite'])) {
            DB::statement('DROP INDEX IF EXISTS ipcrs_manual_upcr_employee_unique');
        }
        Schema::table('ipcrs', function (Blueprint $table) {
            $table->dropIndex(['upcr_id', 'employee_id']);
            $table->unique(['upcr_id', 'employee_id']);
        });
        Schema::table('office_target_office', fn (Blueprint $table) => $table->dropConstrainedForeignId('upcr_id'));
    }
};
