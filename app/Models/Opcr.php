<?php

namespace App\Models;

use App\Models\Concerns\HasReportWorkflow;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Database\Factories\OpcrFactoryLike;

class Opcr extends Model
{
    use HasReportWorkflow;

    protected $fillable = [
        'evaluation_cycle_id',
        'office_id',
        'title',
        'success_indicators',
        'targets',
        'status',
        'submitted_at', 'returned_at', 'resubmission_count',
        'prepared_by',
        'approved_by',
        'approved_at',
    ];

    protected function casts(): array
    {
        return [
            'approved_at' => 'datetime',
            'submitted_at' => 'datetime', 'returned_at' => 'datetime',
        ];
    }

    public function evaluationCycle(): BelongsTo
    {
        return $this->belongsTo(EvaluationCycle::class);
    }

    public function office(): BelongsTo
    {
        return $this->belongsTo(Office::class);
    }

    public function preparedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'prepared_by');
    }

    public function approvedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'approved_by');
    }

    public function dpcrs(): HasMany
    {
        return $this->hasMany(Dpcr::class);
    }

    public function performanceTargets(): \Illuminate\Database\Eloquent\Relations\MorphMany
    {
        return $this->morphMany(PerformanceTarget::class, 'targetable');
    }


    public static function factoryLike(): OpcrFactoryLike
    {
        return new OpcrFactoryLike();
    }
}
