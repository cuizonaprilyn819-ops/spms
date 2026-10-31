<?php

namespace App\Support;

use App\Models\User;
use Illuminate\Support\Facades\DB;

class TargetActivityLog
{
    public function forUser(User $viewer): ?\Illuminate\Contracts\Pagination\LengthAwarePaginator
    {
        if (!$viewer->hasAnyRole(['Administrator', 'Campus Director', 'Planning Officer', 'Supervisor', 'HR Head'])) return null;

        $uploads = DB::table('target_movs as events')
            ->join('office_targets as targets', 'targets.id', '=', 'events.office_target_id')
            ->join('users as actors', 'actors.id', '=', 'events.user_id')
            ->leftJoin('offices as offices', 'offices.id', '=', 'events.office_id')
            ->selectRaw("events.id as event_id, targets.id as target_id, targets.title as target_title, actors.name as actor, offices.name as office, events.created_at as occurred_at, 'MOV attached' as action, events.original_name as detail");
        $completions = DB::table('office_target_employee as events')
            ->join('office_targets as targets', 'targets.id', '=', 'events.office_target_id')
            ->join('users as actors', 'actors.id', '=', 'events.user_id')
            ->leftJoin('offices as offices', 'offices.id', '=', 'actors.office_id')
            ->whereNotNull('events.completed_at')
            ->selectRaw("events.id as event_id, targets.id as target_id, targets.title as target_title, actors.name as actor, offices.name as office, events.completed_at as occurred_at, 'Marked complete' as action, targets.unit_of_measure as detail");

        foreach ([$uploads, $completions] as $query) {
            if ($viewer->hasRole('Administrator')) continue;
            if ($viewer->hasAnyRole(['Campus Director', 'Planning Officer'])) {
                $query->where('targets.created_by', $viewer->id);
            } else {
                $query->where('offices.id', $viewer->office_id ?? 0)
                    ->whereExists(fn ($q) => $q->selectRaw('1')->from('office_target_office as assigned_office')
                        ->whereColumn('assigned_office.office_target_id', 'targets.id')
                        ->where('assigned_office.office_id', $viewer->office_id ?? 0));
            }
        }

        return DB::query()->fromSub($uploads->unionAll($completions), 'activity')
            ->orderByDesc('occurred_at')->orderByDesc('event_id')->orderBy('action')
            ->paginate(20, ['*'], 'activity_page')->withQueryString();
    }
}
