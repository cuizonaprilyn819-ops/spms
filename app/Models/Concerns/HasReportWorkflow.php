<?php

namespace App\Models\Concerns;

use App\Models\CommitmentPlan;
use App\Models\PcrSubmission;
use App\Models\ReportStatusHistory;
use App\Services\PlanningOrganization;
use Illuminate\Database\Eloquent\Relations\MorphMany;

trait HasReportWorkflow
{
    protected static function bootHasReportWorkflow(): void
    {
        // Multi-campus records use the same boundary in lists, files and exports.
        static::addGlobalScope('campusPlanningAccess', function ($query) {
            $user = auth()->user();
            if (! $user || $user->hasRole('Administrator')) {
                return;
            }
            $visible = app(PlanningOrganization::class)->visiblePlanIds($user);
            $query->where(fn ($q) => $q->whereDoesntHave('commitmentPlan.cycle', fn ($c) => $c->where('multi_campus_enabled', true))
                ->orWhereHas('commitmentPlan', fn ($p) => $p->whereIn('id', $visible)));
        });
    }

    public function commitmentPlan()
    {
        return $this->morphOne(CommitmentPlan::class, 'report');
    }

    public function submissionRecord()
    {
        return $this->morphOne(PcrSubmission::class, 'report');
    }

    public const STATUS_DRAFT = 'draft';

    public const STATUS_SUBMITTED = 'submitted';

    public const STATUS_RETURNED = 'returned';

    public const STATUS_RESUBMITTED = 'resubmitted';

    public const STATUS_APPROVED = 'approved';

    public function statusHistories(): MorphMany
    {
        return $this->morphMany(ReportStatusHistory::class, 'report')->latest();
    }

    public function isApproved(): bool
    {
        return $this->status === self::STATUS_APPROVED;
    }

    public function isEditableBy(int $userId): bool
    {
        return (int) $this->prepared_by === $userId && in_array($this->status, [self::STATUS_DRAFT, self::STATUS_RETURNED], true);
    }
}
