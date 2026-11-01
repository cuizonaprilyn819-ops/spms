<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('movs', function (Blueprint $table) {
            $table->foreignId('performance_target_id')->nullable()->constrained()->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('movs', fn (Blueprint $table) => $table->dropConstrainedForeignId('performance_target_id'));
    }
};
