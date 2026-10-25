<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('position_titles', function (Blueprint $table) {
            $table->unsignedBigInteger('legacy_csc_item_id')->nullable()->change();
        });
    }

    public function down(): void
    {
        Schema::table('position_titles', function (Blueprint $table) {
            $table->unsignedBigInteger('legacy_csc_item_id')->nullable(false)->change();
        });
    }
};
