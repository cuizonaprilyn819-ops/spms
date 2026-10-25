<?php

namespace App\Models\Concerns;

use Illuminate\Database\Eloquent\Relations\MorphMany;

trait HasReportWorkflow
{
    public const STATUS_DRAFT = 'draft';
    public const STATUS_SUBMITTED = 'submitted';
    public const STATUS_RETURNED = 'returned';
    public const STATUS_RESUBMITTED = 'resubmitted';
    public const STATUS_APPROVED = 'approved';

    public function statusHistories(): MorphMany
    {
        return $this->morphMany(\App\Models\ReportStatusHistory::class, 'report')->latest();
    }

    public function isApproved(): bool { return $this->status === self::STATUS_APPROVED; }
    public function isEditableBy(int $userId): bool
    {
        return (int) $this->prepared_by === $userId && in_array($this->status, [self::STATUS_DRAFT, self::STATUS_RETURNED], true);
    }
}
