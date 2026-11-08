<?php

namespace App\Support;

use App\Models\OfficeTarget;
use App\Models\User;
use App\Services\PlanningOrganization;
use App\Services\TargetHierarchy;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Support\Facades\DB;

class TargetActivityLog
{
    public function forUser(User $viewer): ?LengthAwarePaginator
    {
        if (! $viewer->hasAnyRole(['Administrator', 'Planning Officer', 'Campus Director', 'Supervisor', 'Division Head', 'Unit Head', 'HR Head'])) {
            return null;
        }

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
            if (! $viewer->hasRole('Administrator')) {
                $query->whereIn('targets.id', OfficeTarget::select('id'));
                $query->where(fn ($q) => $q->whereNull('targets.performance_target_id')->orWhereNull('offices.id')
                    ->orWhereIn('offices.id', app(PlanningOrganization::class)->accessibleOfficeIds($viewer)));
            }
            if ($viewer->hasAnyRole(['Administrator', 'Planning Officer'])) {
                continue;
            }
            if ($viewer->hasRole('Campus Director')) {
                $query->where('targets.created_by', $viewer->id);
            } else {
                $query->whereIn('offices.id', app(TargetHierarchy::class)->managedOfficeIds($viewer))
                    ->whereExists(fn ($q) => $q->selectRaw('1')->from('office_target_office as assigned_office')
                        ->whereColumn('assigned_office.office_target_id', 'targets.id')
                        ->whereColumn('assigned_office.office_id', 'offices.id'));
            }
        }

        return DB::query()->fromSub($uploads->unionAll($completions), 'activity')
            ->orderByDesc('occurred_at')->orderByDesc('event_id')->orderBy('action')
            ->paginate(20, ['*'], 'activity_page')->withQueryString();
    }
}
