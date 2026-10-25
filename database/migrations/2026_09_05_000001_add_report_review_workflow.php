<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        foreach (['opcrs', 'dpcrs', 'ipcrs'] as $tableName) {
            Schema::table($tableName, function (Blueprint $table): void {
                $table->timestamp('submitted_at')->nullable()->after('status');
                $table->timestamp('returned_at')->nullable()->after('submitted_at');
                $table->unsignedInteger('resubmission_count')->default(0)->after('returned_at');
            });
        }

        Schema::table('upcrs', function (Blueprint $table): void {
            $table->string('status')->default('draft')->after('targets');
            $table->timestamp('submitted_at')->nullable()->after('status');
            $table->timestamp('returned_at')->nullable()->after('submitted_at');
            $table->unsignedInteger('resubmission_count')->default(0)->after('returned_at');
            $table->foreignId('approved_by')->nullable()->after('prepared_by')->constrained('users')->nullOnDelete();
            $table->timestamp('approved_at')->nullable()->after('approved_by');
        });

        Schema::create('report_status_histories', function (Blueprint $table): void {
            $table->id();
            $table->morphs('report');
            $table->string('from_status')->nullable();
            $table->string('to_status');
            $table->text('remarks')->nullable();
            $table->foreignId('acted_by')->constrained('users')->cascadeOnDelete();
            $table->timestamps();
            $table->index(['report_type', 'report_id', 'created_at'], 'report_history_timeline');
        });

        $officeHeadRole = DB::table('roles')->where('name', 'Office Head')->first();
        $supervisorRole = DB::table('roles')->where('name', 'Supervisor')->first();
        if ($officeHeadRole && $supervisorRole) {
            DB::table('model_has_roles')->where('role_id', $officeHeadRole->id)->get()->each(function ($assignment) use ($supervisorRole): void {
                DB::table('model_has_roles')->insertOrIgnore([
                    'role_id' => $supervisorRole->id,
                    'model_type' => $assignment->model_type,
                    'model_id' => $assignment->model_id,
                ]);
            });
            DB::table('model_has_roles')->where('role_id', $officeHeadRole->id)->delete();
            DB::table('roles')->where('id', $officeHeadRole->id)->delete();
        } elseif ($officeHeadRole) {
            DB::table('roles')->where('id', $officeHeadRole->id)->update(['name' => 'Supervisor']);
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('report_status_histories');
        Schema::table('upcrs', function (Blueprint $table): void {
            $table->dropConstrainedForeignId('approved_by');
            $table->dropColumn(['status', 'submitted_at', 'returned_at', 'resubmission_count', 'approved_at']);
        });
        foreach (['opcrs', 'dpcrs', 'ipcrs'] as $tableName) {
            Schema::table($tableName, fn (Blueprint $table) => $table->dropColumn(['submitted_at', 'returned_at', 'resubmission_count']));
        }
    }
};
