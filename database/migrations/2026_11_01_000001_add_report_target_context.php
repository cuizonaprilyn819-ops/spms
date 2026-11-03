<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        foreach (['opcrs', 'dpcrs', 'upcrs', 'ipcrs'] as $name) Schema::table($name, fn (Blueprint $table) => $table->json('target_context')->nullable());
    }
    public function down(): void
    {
        foreach (['opcrs', 'dpcrs', 'upcrs', 'ipcrs'] as $name) Schema::table($name, fn (Blueprint $table) => $table->dropColumn('target_context'));
    }
};
