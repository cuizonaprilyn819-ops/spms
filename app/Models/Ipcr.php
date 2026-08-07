<?php

namespace App\Models;

use Database\Factories\IpcrFactoryLike;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Ipcr extends Model
{
    public const STATUS_DRAFT = 'draft';
    public const STATUS_APPROVED = 'approved';

    protected $fillable = [
        'upcr_id',
        'employee_id',
        'title',
        'success_indicators',
        'targets',
        'status',
        'prepared_by',
        'approved_by',
        'approved_at',
    ];

    protected function casts(): array
    {
        return [
            'approved_at' => 'datetime',
        ];
    }

    public function upcr(): BelongsTo
    {
        return $this->belongsTo(Upcr::class);
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

    public function isApproved(): bool
    {
        return $this->status === self::STATUS_APPROVED;
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
