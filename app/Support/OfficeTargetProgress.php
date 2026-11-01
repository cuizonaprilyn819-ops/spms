<?php

namespace App\Support;

use App\Models\OfficeTarget;
use App\Models\User;
use Illuminate\Support\Collection;

class OfficeTargetProgress
{
    public function forUser(User $user): Collection
    {
        $managed = app(\App\Services\TargetHierarchy::class)->managedOfficeIds($user);
        $head = count($managed) > 0;
        $director = $user->hasRole('Campus Director');
        $query = OfficeTarget::whereNull('performance_target_id');
        if ($head) {
            $query->whereHas('offices', fn ($q) => $q->whereIn('offices.id', $managed));
        } elseif ($director) {
            $query->where('created_by', $user->id);
        } elseif (! $user->hasAnyRole(['Administrator', 'Planning Officer'])) {
            $query->whereHas('assignedEmployees', fn ($q) => $q->whereKey($user->id));
        }
        $personal = ! $head && ! $director && ! $user->hasAnyRole(['Administrator', 'Planning Officer']);

        return $query->with(['offices', 'assignedEmployees' => function ($q) use ($head, $personal, $user, $managed) {
            if ($head) {
                $q->whereIn('users.office_id', $managed);
            } elseif ($personal) {
                $q->whereKey($user->id);
            }
        }])->orderBy('deadline')->get()->map(function ($target) use ($head, $personal, $user, $managed) {
            $people = $target->assignedEmployees->filter(fn ($person) => $target->offices->contains('id', $person->office_id));
            $required = $personal ? $people->first()?->pivot->allocated_target_value
                : ($head ? $target->offices->whereIn('id', $managed)->sum(fn ($office) => (float) $office->pivot->allocated_target_value) : $target->target_value);
            $known = $required !== null && (float) $required > 0;
            $done = $people->sum(fn ($person) => $person->pivot->allocated_target_value === null ? 0
                : (float) ($person->pivot->completed_value ?? ($person->pivot->completed_at ? $person->pivot->allocated_target_value : 0)));
            $percent = $known ? min(100, round(100 * $done / (float) $required, 1)) : null;

            return ['id' => $target->id, 'title' => $target->title, 'unit' => $target->unit_of_measure,
                'required' => $required, 'completed' => $done, 'percent' => $percent,
                'deadline' => $target->deadline?->format('M d, Y'),
                'overdue' => $target->deadline?->endOfDay()->isPast() && ($percent === null || $percent < 100),
                'status' => ! $known ? 'Allocation needed' : ($percent >= 100 ? 'Reported complete' : ($done > 0 ? 'In progress' : 'Not started'))];
        });
    }
}
