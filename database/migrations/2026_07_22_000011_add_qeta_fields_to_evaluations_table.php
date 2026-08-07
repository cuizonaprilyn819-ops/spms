<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('evaluations', function (Blueprint $table) {
            $table->decimal('quality_rating', 4, 2)->nullable()->after('performance_target_id');
            $table->decimal('efficiency_rating', 4, 2)->nullable()->after('quality_rating');
            $table->decimal('timeliness_rating', 4, 2)->nullable()->after('efficiency_rating');
            $table->string('adjectival_rating')->nullable()->after('computed_rating');
        });

        DB::table('evaluations')->orderBy('id')->each(function (object $evaluation): void {
            $rating = (float) $evaluation->rating;

            DB::table('evaluations')
                ->where('id', $evaluation->id)
                ->update([
                    'quality_rating' => $rating,
                    'efficiency_rating' => $rating,
                    'timeliness_rating' => $rating,
                    'adjectival_rating' => match (true) {
                        $rating >= 4.5 => 'Outstanding',
                        $rating >= 3.5 => 'Very Satisfactory',
                        $rating >= 2.5 => 'Satisfactory',
                        $rating >= 1.5 => 'Unsatisfactory',
                        default => 'Poor',
                    },
                ]);
        });
    }

    public function down(): void
    {
        Schema::table('evaluations', function (Blueprint $table) {
            $table->dropColumn([
                'quality_rating',
                'efficiency_rating',
                'timeliness_rating',
                'adjectival_rating',
            ]);
        });
    }
};
