<?php

namespace App\Models;

use App\Models\Concerns\HasReportWorkflow;

use Database\Factories\IpcrFactoryLike;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Ipcr extends Model
{
    use HasReportWorkflow;

    protected $fillable = [
        'upcr_id',
        'office_target_id',
        'quality_rating', 'efficiency_rating', 'timeliness_rating', 'average_rating', 'rating_remarks',
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

    public function upcr(): BelongsTo
    {
        return $this->belongsTo(Upcr::class);
    }

    public function officeTarget(): BelongsTo { return $this->belongsTo(OfficeTarget::class); }

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


    public function performanceTargets(): \Illuminate\Database\Eloquent\Relations\MorphMany
    {
        return $this->morphMany(PerformanceTarget::class, 'targetable');
    }

    public static function factoryLike(): IpcrFactoryLike
    {
        return new IpcrFactoryLike();
    }
}
