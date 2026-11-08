<?php

namespace App\Services;

use App\Models\CommitmentPlan;
use App\Models\Office;
use App\Models\PlanningGrant;
use App\Models\User;
use Illuminate\Validation\ValidationException;

class PlanningOrganization
{
    public function hasGrant(User $user, Office $office): bool
    {
        if ($user->hasRole('Administrator')) {
            return true;
        }
        if (! $user->hasRole('Planning Officer')) {
            return false;
        }
        if ($office->organization_scope === 'legacy') {
            return (int) $office->campus === (int) config('hinunangan.campus_id');
        }

        return PlanningGrant::where('user_id', $user->id)->where(fn ($q) => $q->where('scope', 'central')
            ->when($office->campus_id, fn ($q) => $q->orWhere(fn ($q) => $q->where('scope', 'campus')->where('campus_id', $office->campus_id))))->exists();
    }

    public function accessibleOfficeIds(User $user): array
    {
        if ($user->hasRole('Administrator')) {
            return Office::pluck('id')->all();
        }
        $offices = Office::get(['id', 'parent_office_id', 'head_id', 'organization_scope', 'campus', 'campus_id'])->keyBy('id');
        $planner = $user->hasRole('Planning Officer');
        $grants = $planner ? PlanningGrant::where('user_id', $user->id)->get() : collect();
        $central = $grants->contains('scope', 'central');
        $campuses = $grants->where('scope', 'campus')->pluck('campus_id')->all();

        return $offices->filter(function ($office) use ($user, $offices, $planner, $central, $campuses) {
            if ((int) $user->office_id === $office->id) {
                return true;
            }
            if ($planner && ($office->organization_scope === 'legacy' ? (int) $office->campus === (int) config('hinunangan.campus_id') : ($central || in_array($office->campus_id, $campuses, true)))) {
                return true;
            }
            $seen = [];
            while ($office && ! isset($seen[$office->id])) {
                if ((int) $office->head_id === $user->id) {
                    return true;
                }
                $seen[$office->id] = true;
                $office = $offices->get($office->parent_office_id);
            }

            return false;
        })->modelKeys();
    }

    public function isScoped(Office $office): bool
    {
        return $office->organization_scope !== 'legacy';
    }

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
        if ($office->organization_scope === 'legacy' && (int) $office->campus !== (int) config('hinunangan.campus_id')) {
            throw ValidationException::withMessages(['office_id' => 'Choose an active Hinunangan Campus 6 office.']);
        }
        $this->ancestors($office);
        $expected = ['office' => null, 'division' => 'office', 'unit' => 'division'];
        if (! array_key_exists($office->planning_level, $expected) || ! $office->hierarchy_verified_at || ! trim($office->mandate ?? '') || ! $office->head_id || ! $office->planning_reviewer_id || ! $office->is_active) {
            throw ValidationException::withMessages(['office_id' => 'Verify this office’s reporting level, mandate, head and reviewer first.']);
        }
        $campusRoot = $office->organization_scope === 'campus' && $office->planning_level === 'office';
        if ($office->organization_scope === 'campus' && (! $office->campus_id || ! $office->campusRecord?->is_active)) {
            throw ValidationException::withMessages(['campus_id' => 'Assign an active verified campus.']);
        }
        if ($office->organization_scope === 'university' && ($office->campus_id || $office->planning_level !== 'office')) {
            throw ValidationException::withMessages(['office_id' => 'The university root is an office without a campus assignment.']);
        }
        if ($campusRoot ? ($office->parentOffice?->organization_scope !== 'university' || $office->parentOffice?->planning_level !== 'office') : ($office->planning_level === 'office' ? $office->parent_office_id !== null : $office->parentOffice?->planning_level !== $expected[$office->planning_level])) {
            throw ValidationException::withMessages(['office_id' => 'Use Office → Division → Unit reporting relationships.']);
        }
        if ((int) $office->head_id === (int) $office->planning_reviewer_id || (int) $office->head?->office_id !== (int) $office->id) {
            throw ValidationException::withMessages(['office_id' => 'The head must belong to this office and have a separate reviewer.']);
        }
        if (! $office->planningReviewer?->hasAnyRole(['Administrator', 'Planning Officer', 'Campus Director', 'Supervisor', 'HR Head', 'HR'])) {
            throw ValidationException::withMessages(['planning_reviewer_id' => 'Assign a reviewer with an existing management role.']);
        }
        if ($office->parentOffice && ! $campusRoot && ($office->parentOffice->campus !== $office->campus || $office->parentOffice->campus_id !== $office->campus_id || $office->parentOffice->organization_scope !== $office->organization_scope)) {
            throw ValidationException::withMessages(['office_id' => 'Parent and child offices must belong to the same campus.']);
        }
        if ($office->parentOffice) {
            $this->verify($office->parentOffice);
        }
    }

    public function canView(User $user, CommitmentPlan $plan): bool
    {
        if ($this->hasGrant($user, $plan->office) || in_array($user->id, [$plan->prepared_by, $plan->reviewer_id, $plan->employee_id], true)) {
            return true;
        }

        return Office::whereIn('id', $this->ancestors($plan->office))->where('head_id', $user->id)->exists();
    }

    public function canPrepare(User $user, CommitmentPlan $plan): bool
    {
        return $this->hasGrant($user, $plan->office) || $plan->prepared_by === $user->id || $plan->office->head_id === $user->id;
    }

    public function isOwner(User $user, Office $office): bool
    {
        return $this->hasGrant($user, $office) || (int) $office->head_id === $user->id;
    }

    public function visiblePlanIds(User $user): array
    {
        return CommitmentPlan::with('office')->get()->filter(fn ($p) => $this->canView($user, $p))->modelKeys();
    }
}
