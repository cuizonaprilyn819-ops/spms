<?php

namespace App\Services;

use App\Models\CommitmentPlan;
use App\Models\Office;
use App\Models\User;
use Illuminate\Validation\ValidationException;

class PlanningOrganization
{
    public function ancestors(Office $office): array
    {
        $ids = [];
        while ($office) {
            if (in_array($office->id, $ids)) {
                throw ValidationException::withMessages(['office_id' => 'The reporting structure contains a cycle.']);
            }
            $ids[] = $office->id;
            $office = $office->parentOffice;
        }

        return $ids;
    }

    public function verify(Office $office): void
    {
        $this->ancestors($office);
        $expected = ['office' => null, 'division' => 'office', 'unit' => 'division'];
        if (! array_key_exists($office->planning_level, $expected) || ! $office->hierarchy_verified_at || ! trim($office->mandate ?? '') || ! $office->head_id || ! $office->planning_reviewer_id || ! $office->is_active) {
            throw ValidationException::withMessages(['office_id' => 'Verify this office’s reporting level, mandate, head and reviewer first.']);
        }
        if ($office->planning_level === 'office' ? $office->parent_office_id !== null : $office->parentOffice?->planning_level !== $expected[$office->planning_level]) {
            throw ValidationException::withMessages(['office_id' => 'Use Office → Division → Unit reporting relationships.']);
        }
        if ((int) $office->head_id === (int) $office->planning_reviewer_id || (int) $office->head?->office_id !== (int) $office->id) {
            throw ValidationException::withMessages(['office_id' => 'The head must belong to this office and have a separate reviewer.']);
        }
        if (! $office->planningReviewer?->hasAnyRole(['Administrator', 'Campus Director', 'Supervisor', 'HR Head', 'HR'])) {
            throw ValidationException::withMessages(['planning_reviewer_id' => 'Assign a reviewer with an existing management role.']);
        }
        if ($office->parentOffice && $office->parentOffice->campus !== $office->campus) {
            throw ValidationException::withMessages(['office_id' => 'Parent and child offices must belong to the same campus.']);
        }
        if ($office->parentOffice) {
            $this->verify($office->parentOffice);
        }
    }

    public function canView(User $user, CommitmentPlan $plan): bool
    {
        if ($user->hasRole('Administrator') || in_array($user->id, [$plan->prepared_by, $plan->reviewer_id, $plan->employee_id], true) || ($plan->level === 'opcr' && $user->hasRole('Planning Officer'))) {
            return true;
        }

        return Office::whereIn('id', $this->ancestors($plan->office))->where('head_id', $user->id)->exists();
    }

    public function canPrepare(User $user, CommitmentPlan $plan): bool
    {
        return $user->hasRole('Administrator')
            || $plan->prepared_by === $user->id
            || $plan->office->head_id === $user->id
            || ($plan->level === 'opcr' && $user->hasRole('Planning Officer'));
    }

    public function isOwner(User $user, Office $office): bool
    {
        return $user->hasRole('Administrator') || (int) $office->head_id === $user->id;
    }

    public function visiblePlanIds(User $user): array
    {
        return CommitmentPlan::with('office')->get()->filter(fn ($p) => $this->canView($user, $p))->modelKeys();
    }
}
