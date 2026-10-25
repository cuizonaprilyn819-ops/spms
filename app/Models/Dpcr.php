<?php

namespace App\Models;

use App\Models\Concerns\HasReportWorkflow;

use Database\Factories\DpcrFactoryLike;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Dpcr extends Model
{
    use HasReportWorkflow;

    protected $fillable = [
        'opcr_id',
        'employee_id',
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

    public function opcr(): BelongsTo
    {
        return $this->belongsTo(Opcr::class);
    }

    public function employee(): BelongsTo
    {
        return $this->belongsTo(User::class, 'employee_id');
    }

    public function preparedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'prepared_by');
    }

    public function approvedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'approved_by');
    }

    public function upcrs(): HasMany
    {
        return $this->hasMany(Upcr::class);
    }

    public function performanceTargets(): \Illuminate\Database\Eloquent\Relations\MorphMany
    {
        return $this->morphMany(PerformanceTarget::class, 'targetable');
    }


    public static function factoryLike(): DpcrFactoryLike
    {
        return new DpcrFactoryLike();
    }
}
