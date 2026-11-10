<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('evaluation_cycles', function (Blueprint $table) {
            // Null preserves the workflow of periods activated before this option existed.
            $table->string('planning_root_level', 20)->nullable();
        });
    }

    public function down(): void
    {
        Schema::table('evaluation_cycles', fn (Blueprint $table) => $table->dropColumn('planning_root_level'));
    }
};
