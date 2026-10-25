<?php
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
return new class extends Migration {
    public function up(): void {
        Schema::table('ipcrs', function (Blueprint $table) {
            foreach (['quality_rating', 'efficiency_rating', 'timeliness_rating', 'average_rating'] as $column) $table->decimal($column, 4, 2)->nullable();
            $table->text('rating_remarks')->nullable();
        });
    }
    public function down(): void {
        Schema::table('ipcrs', fn (Blueprint $table) => $table->dropColumn(['quality_rating', 'efficiency_rating', 'timeliness_rating', 'average_rating', 'rating_remarks']));
    }
};
