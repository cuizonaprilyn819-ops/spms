<?php

namespace App\Services;

use App\Models\{Office, OfficeTarget, Upcr, User};

class TargetHierarchy
{
    /** A head sees their own office and its descendants, without duplicating allocations. */
    public function managedOfficeIds(User $user): array
    {
        if (! $user->office_id || (! $user->hasAnyRole(['Supervisor', 'HR Head']) && (int) $user->office?->head_id !== $user->id)) return [];
        $ids = [(int) $user->office_id];
        $frontier = $ids;
        while ($frontier) {
            $frontier = Office::where('campus', $user->office->campus)->whereIn('parent_office_id', $frontier)->whereNotIn('id', $ids)->pluck('id')->all();
            $ids = array_merge($ids, $frontier);
        }
        return $ids;
    }

    public function canView(User $user, OfficeTarget $target): bool
    {
        if ($user->hasAnyRole(['Administrator', 'Planning Officer', 'Campus Director'])) return true;
        if ($target->offices()->whereIn('offices.id', $this->managedOfficeIds($user))->exists()) return true;
        return $target->assignedEmployees()->whereKey($user->id)->exists();
    }

    public function reportingOfficeIds(array $officeIds): array
    {
        $ids = [];
        foreach (Office::whereIn('id', $officeIds)->get() as $office) {
            foreach (app(PlanningOrganization::class)->ancestors($office) as $id) $ids[$id] = $id;
        }
        return array_values($ids);
    }

    public function eligibleUpcrs(OfficeTarget $target, Office $office)
    {
        $ancestors = app(PlanningOrganization::class)->ancestors($office);
        return Upcr::whereDoesntHave('commitmentPlan')
            ->whereHas('employee', fn ($q) => $q->where('office_id', $office->id))
            ->whereHas('dpcr', fn ($q) => $q->whereHas('employee', fn ($person) => $person->whereIn('office_id', $ancestors)))
            ->whereHas('dpcr.opcr', fn ($q) => $q->where('evaluation_cycle_id', $target->evaluation_cycle_id)->whereIn('office_id', $ancestors))
            ->when($office->head_id, fn ($q) => $q->where('employee_id', $office->head_id))
            ->with(['employee', 'dpcr.opcr.evaluationCycle'])->orderByDesc('id');
    }

    public function linkAvailableUpcrs(OfficeTarget $target): void
    {
        foreach ($target->offices()->get() as $office) {
            if ($office->pivot->upcr_id) continue;
            $candidates = $this->eligibleUpcrs($target, $office)->get();
            $approved = $candidates->where('status', Upcr::STATUS_APPROVED);
            $choice = $approved->count() === 1 ? $approved->first() : ($candidates->count() === 1 ? $candidates->first() : null);
            if ($choice) $target->offices()->updateExistingPivot($office->id, ['upcr_id' => $choice->id]);
        }
        $target->unsetRelation('offices');
    }

    public function findUpcr(OfficeTarget $target, User $employee): ?Upcr
    {
        $office = $target->offices()->whereKey($employee->office_id ?? 0)->first();
        $upcr = null;
        if ($office) {
            $query = $this->eligibleUpcrs($target, $office);
            if ($office->pivot->upcr_id) $upcr = $query->whereKey($office->pivot->upcr_id)->first();
            else {
                $choices = $query->get();
                $approved = $choices->where('status', Upcr::STATUS_APPROVED);
                if ($approved->count() === 1) $upcr = $approved->first();
                elseif ($choices->count() === 1) $upcr = $choices->first();
            }
        }
        return $upcr;
    }
}
