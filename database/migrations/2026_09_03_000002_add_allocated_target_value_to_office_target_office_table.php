<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::table('office_target_office', function (Blueprint $table) {
            $table->decimal('allocated_target_value', 12, 2)->nullable()->after('office_id');
        });
    }

    public function down(): void
    {
        Schema::table('office_target_office', function (Blueprint $table) {
            $table->dropColumn('allocated_target_value');
        });
    }
};
