<?php

namespace App\Support;

use App\Models\OfficeTarget;
use App\Models\User;
use Illuminate\Support\Collection;

class OfficeTargetProgress
{
    public function forUser(User $user): Collection
    {
        $head = $user->hasAnyRole(['Supervisor', 'HR Head']);
        $director = $user->hasRole('Campus Director');
        $query = OfficeTarget::whereNull('performance_target_id');
        if ($head) {
            $query->whereHas('offices', fn ($q) => $q->whereKey($user->office_id ?? 0));
        } elseif ($director) {
            $query->where('created_by', $user->id);
        } elseif (! $user->hasRole('Administrator')) {
            $query->whereHas('assignedEmployees', fn ($q) => $q->whereKey($user->id));
        }
        $personal = ! $head && ! $director && ! $user->hasRole('Administrator');

        return $query->with(['offices', 'assignedEmployees' => function ($q) use ($head, $personal, $user) {
            if ($head) {
                $q->where('users.office_id', $user->office_id ?? 0);
            } elseif ($personal) {
                $q->whereKey($user->id);
            }
        }])->orderBy('deadline')->get()->map(function ($target) use ($head, $personal, $user) {
            $people = $target->assignedEmployees->filter(fn ($person) => $target->offices->contains('id', $person->office_id));
            $required = $personal ? $people->first()?->pivot->allocated_target_value
                : ($head ? $target->offices->firstWhere('id', $user->office_id)?->pivot->allocated_target_value : $target->target_value);
            $known = $required !== null && (float) $required > 0;
            $done = $people->sum(fn ($person) => $person->pivot->allocated_target_value === null ? 0
                : min((float) $person->pivot->allocated_target_value, (float) ($person->pivot->completed_value ?? ($person->pivot->completed_at ? $person->pivot->allocated_target_value : 0))));
            $percent = $known ? min(100, round(100 * $done / (float) $required, 1)) : null;

            return ['id' => $target->id, 'title' => $target->title, 'unit' => $target->unit_of_measure,
                'required' => $required, 'completed' => $done, 'percent' => $percent,
                'deadline' => $target->deadline?->format('M d, Y'),
                'overdue' => $target->deadline?->endOfDay()->isPast() && ($percent === null || $percent < 100),
                'status' => ! $known ? 'Allocation needed' : ($percent >= 100 ? 'Reported complete' : ($done > 0 ? 'In progress' : 'Not started'))];
        });
    }
}
