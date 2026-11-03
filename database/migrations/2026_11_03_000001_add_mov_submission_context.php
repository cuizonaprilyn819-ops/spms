<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        foreach (['movs', 'target_movs'] as $name) Schema::table($name, fn (Blueprint $table) => $table->json('submission_context')->nullable());
    }
    public function down(): void
    {
        foreach (['movs', 'target_movs'] as $name) Schema::table($name, fn (Blueprint $table) => $table->dropColumn('submission_context'));
    }
};
