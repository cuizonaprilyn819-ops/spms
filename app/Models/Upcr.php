<?php

namespace App\Models;

use App\Models\Concerns\HasReportWorkflow;

use Database\Factories\UpcrFactoryLike;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Upcr extends Model
{
    use HasReportWorkflow;
    protected $fillable = [
        'dpcr_id',
        'employee_id',
        'title',
        'success_indicators',
        'targets',
        'prepared_by',
        'status', 'submitted_at', 'returned_at', 'resubmission_count', 'approved_by', 'approved_at',
    ];

    protected function casts(): array
    {
        return ['submitted_at' => 'datetime', 'returned_at' => 'datetime', 'approved_at' => 'datetime'];
    }

    public function dpcr(): BelongsTo
    {
        return $this->belongsTo(Dpcr::class);
    }

    public function employee(): BelongsTo
    {
        return $this->belongsTo(User::class, 'employee_id');
    }

    public function preparedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'prepared_by');
    }

    public function approvedBy(): BelongsTo { return $this->belongsTo(User::class, 'approved_by'); }

    public function ipcrs(): HasMany
    {
        return $this->hasMany(Ipcr::class);
    }

    public function performanceTargets(): \Illuminate\Database\Eloquent\Relations\MorphMany
    {
        return $this->morphMany(PerformanceTarget::class, 'targetable');
    }

    public static function factoryLike(): UpcrFactoryLike
    {
        return new UpcrFactoryLike();
    }
}
