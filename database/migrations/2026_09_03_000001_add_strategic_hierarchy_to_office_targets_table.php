<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::table('office_targets', function (Blueprint $table) {
            $table->string('strategic_goal')->nullable()->after('evaluation_cycle_id');
            $table->text('strategic_goal_description')->nullable()->after('strategic_goal');
            $table->string('strategic_objective')->nullable()->after('strategic_goal_description');
        });
    }

    public function down(): void
    {
        Schema::table('office_targets', function (Blueprint $table) {
            $table->dropColumn(['strategic_goal', 'strategic_goal_description', 'strategic_objective']);
        });
    }
};
