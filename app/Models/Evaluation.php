<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Evaluation extends Model
{
    protected $fillable = [
        'employee_id',
        'performance_target_id',
        'quality_rating',
        'efficiency_rating',
        'timeliness_rating',
        'rating',
        'computed_rating',
        'adjectival_rating',
        'remarks',
        'rated_by',
    ];

    protected function casts(): array
    {
        return [
            'quality_rating' => 'decimal:2',
            'efficiency_rating' => 'decimal:2',
            'timeliness_rating' => 'decimal:2',
            'rating' => 'decimal:2',
            'computed_rating' => 'decimal:2',
        ];
    }

    public function employee(): BelongsTo
    {
        return $this->belongsTo(User::class, 'employee_id');
    }

    public function performanceTarget(): BelongsTo
    {
        return $this->belongsTo(PerformanceTarget::class);
    }

    public function ratedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'rated_by');
    }

    public static function computeRating(float $rating, float $weight): float
    {
        return round($rating * ($weight / 100), 2);
    }

    public static function computeAverage(float $quality, float $efficiency, float $timeliness): float
    {
        return round(($quality + $efficiency + $timeliness) / 3, 2);
    }

    public static function adjectivalRating(float $rating): string
    {
        return match (true) {
            $rating >= 4.5 => 'Outstanding',
            $rating >= 3.5 => 'Very Satisfactory',
            $rating >= 2.5 => 'Satisfactory',
            $rating >= 1.5 => 'Unsatisfactory',
            default => 'Poor',
        };
    }
}
