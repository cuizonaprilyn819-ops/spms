<?php

namespace App\Models;

use Database\Factories\AccomplishmentFactoryLike;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Accomplishment extends Model
{
    protected $fillable = [
        'performance_target_id',
        'details',
        'completion_percentage',
        'submitted_by',
    ];

    protected function casts(): array
    {
        return [
            'completion_percentage' => 'decimal:2',
        ];
    }

    public function performanceTarget(): BelongsTo
    {
        return $this->belongsTo(PerformanceTarget::class);
    }

    public function submittedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'submitted_by');
    }

    public static function factoryLike(): AccomplishmentFactoryLike
    {
        return new AccomplishmentFactoryLike();
    }
}
