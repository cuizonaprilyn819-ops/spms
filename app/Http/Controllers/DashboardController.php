<?php

namespace App\Http\Controllers;

use App\Models\Accomplishment;
use App\Models\Dpcr;
use App\Models\Evaluation;
use App\Models\EvaluationCycle;
use App\Models\Ipcr;
use App\Models\Mov;
use App\Models\Office;
use App\Models\OfficeTarget;
use App\Models\Opcr;
use App\Models\PerformanceTarget;
use App\Models\PositionTitle;
use App\Models\TargetMov;
use App\Models\Upcr;
use App\Models\User;
use App\Support\OfficeTargetProgress;
use App\Support\TargetActivityLog;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\View\View;

class DashboardController extends Controller
{
    public function __invoke(): View
    {
        $officeProgress = app(OfficeTargetProgress::class)->forUser(request()->user());
        $targetActivity = app(TargetActivityLog::class)->forUser(request()->user());
        if (! request()->user()->hasRole('Administrator')) {
            return view('dashboard', [
                'personalDashboard' => $this->personalDashboard(request()->user(), $officeProgress),
                'officeProgress' => $officeProgress,
                'targetActivity' => $targetActivity,
            ]);
        }

        $models = [
            'Employees' => User::class,
            'Offices' => Office::class,
            'Position Titles' => PositionTitle::class,
            'Evaluation Periods' => EvaluationCycle::class,
            'OPCRs' => Opcr::class,
            'DPCRs' => Dpcr::class,
            'UPCRs' => Upcr::class,
            'IPCRs' => Ipcr::class,
            'Performance Targets' => PerformanceTarget::class,
            'Accomplishments' => Accomplishment::class,
            'Evaluations' => Evaluation::class,
            'MOV Files' => Mov::class,
        ];
        // Scalar subqueries keep counts live while sharing one database round trip.
        $summary = DB::query();
        foreach (array_values($models) as $index => $model) {
            $countQuery = $model::query();
            if ($model === PerformanceTarget::class) {
                $countQuery->currentPlanningRevision();
            }
            $summary->selectSub($countQuery->selectRaw('COUNT(*)'), 'total_'.$index);
        }
        $summary->selectSub(Office::where('is_active', true)->selectRaw('COUNT(*)'), 'active_offices')
            ->selectSub(Office::where('is_active', false)->selectRaw('COUNT(*)'), 'inactive_offices')
            ->selectSub(Accomplishment::where('completion_percentage', '>=', 100)->selectRaw('COUNT(*)'), 'completed')
            ->selectSub(Accomplishment::whereBetween('completion_percentage', [1, 99.99])->selectRaw('COUNT(*)'), 'in_progress')
            ->selectSub(Accomplishment::where('completion_percentage', '<=', 0)->selectRaw('COUNT(*)'), 'not_started');
        $totals = $summary->first();
        $counts = [];
        foreach (array_keys($models) as $index => $label) {
            $counts[$label] = (int) $totals->{'total_'.$index};
        }

        $charts = [
            $this->chart('Performance records', [
                'OPCR' => $counts['OPCRs'], 'DPCR' => $counts['DPCRs'],
                'UPCR' => $counts['UPCRs'], 'IPCR' => $counts['IPCRs'],
            ]),
            $this->chart('Commitment status', $this->combineStatuses()),
            $this->chart('Evaluation periods', $this->group(EvaluationCycle::class, 'status')),
            $this->chart('Offices', [
                'Active' => $totals->active_offices,
                'Inactive' => $totals->inactive_offices,
            ]),
            $this->chart('Employees by role', DB::table('roles')
                ->leftJoin('model_has_roles', 'roles.id', '=', 'model_has_roles.role_id')
                ->selectRaw('roles.name as label, COUNT(model_has_roles.role_id) as total')
                ->groupBy('roles.id', 'roles.name')->pluck('total', 'label')->all()),
            $this->chart('Targets by form', PerformanceTarget::query()
                ->currentPlanningRevision()
                ->selectRaw('targetable_type as label, COUNT(*) as total')
                ->groupBy('targetable_type')->pluck('total', 'label')->mapWithKeys(
                    fn ($value, $key) => [class_basename($key) => $value]
                )->all()),
            $this->chart('Accomplishment progress', [
                'Completed' => $totals->completed,
                'In progress' => $totals->in_progress,
                'Not started' => $totals->not_started,
            ]),
            $this->chart('Evaluation ratings', $this->group(Evaluation::class, 'adjectival_rating', 'Unrated')),
            $this->chart('MOV file types', $this->group(Mov::class, 'extension', 'Unknown')),
        ];

        return view('dashboard', compact('counts', 'charts', 'officeProgress', 'targetActivity'));
    }

    private function group(string $model, string $column, string $empty = 'Unspecified'): array
    {
        return $model::query()->selectRaw("COALESCE(NULLIF({$column}, ''), ?) as label, COUNT(*) as total", [$empty])
            ->groupBy('label')->pluck('total', 'label')->all();
    }

    private function combineStatuses(): array
    {
        $totals = [];
        $query = null;
        foreach ([Opcr::class, Dpcr::class, Ipcr::class] as $model) {
            $statuses = $model::query()->selectRaw("COALESCE(NULLIF(status, ''), ?) as label, COUNT(*) as total", ['Unspecified'])
                ->groupBy('label')->toBase();
            $query = $query ? $query->unionAll($statuses) : $statuses;
        }
        foreach ($query->get() as $row) {
            $label = ucfirst($row->label);
            $totals[$label] = ($totals[$label] ?? 0) + $row->total;
        }

        return $totals;
    }

    private function chart(string $title, array $values): array
    {
        return ['title' => $title, 'values' => array_map('intval', $values)];
    }

    private function personalDashboard(User $user, Collection $officeProgress): array
    {
        $targetQuery = PerformanceTarget::query()->where(function ($query) use ($user): void {
            $query->where(function ($formQuery) use ($user): void {
                $formQuery->where('targetable_type', Dpcr::class)
                    ->whereIn('targetable_id', Dpcr::where('employee_id', $user->id)->select('id'));
            })->orWhere(function ($formQuery) use ($user): void {
                $formQuery->where('targetable_type', Upcr::class)
                    ->whereIn('targetable_id', Upcr::where('employee_id', $user->id)->select('id'));
            })->orWhere(function ($formQuery) use ($user): void {
                $formQuery->where('targetable_type', Ipcr::class)
                    ->whereIn('targetable_id', Ipcr::where('employee_id', $user->id)->select('id'));
            });

            if ($user->hasAnyRole(['Supervisor', 'HR Head']) && $user->office_id) {
                $query->orWhere(function ($formQuery) use ($user): void {
                    $formQuery->where('targetable_type', Opcr::class)
                        ->whereIn('targetable_id', Opcr::where('office_id', $user->office_id)->select('id'));
                });
            }
        });

        $targetIds = $targetQuery->pluck('id');
        $progressByTarget = Accomplishment::query()
            ->whereIn('performance_target_id', $targetIds)
            ->selectRaw('performance_target_id, MAX(completion_percentage) as progress')
            ->groupBy('performance_target_id')
            ->pluck('progress', 'performance_target_id');

        $completed = $progressByTarget->filter(fn ($progress) => (float) $progress >= 100)->count();
        $inProgress = $progressByTarget->filter(fn ($progress) => (float) $progress > 0 && (float) $progress < 100)->count();
        $notStarted = max(0, $targetIds->count() - $completed - $inProgress);

        $officeTargets = OfficeTarget::query();
        if ($user->hasAnyRole(['Supervisor', 'HR Head'])) {
            $officeTargets->whereHas('offices', fn ($q) => $q->whereKey($user->office_id ?? 0));
        } elseif ($user->hasAnyRole(['Campus Director', 'Planning Officer'])) {
            $officeTargets->where('created_by', $user->id);
        } else {
            $officeTargets->whereHas('assignedEmployees', fn ($q) => $q->whereKey($user->id));
        }
        $officeTargetIds = $officeTargets->pluck('id');
        $movQuery = TargetMov::whereIn('office_target_id', $officeTargetIds);
        if ($user->hasAnyRole(['Supervisor', 'HR Head'])) {
            $movQuery->where('office_id', $user->office_id ?? 0);
        } elseif (! $user->hasAnyRole(['Campus Director', 'Planning Officer'])) {
            $movQuery->where('user_id', $user->id);
        }
        $completed += $officeProgress->filter(fn ($row) => $row['percent'] !== null && $row['percent'] >= 100)->count();
        $inProgress += $officeProgress->filter(fn ($row) => $row['percent'] !== null && $row['percent'] > 0 && $row['percent'] < 100)->count();
        $notStarted += $officeProgress->filter(fn ($row) => $row['percent'] === null || $row['percent'] <= 0)->count();

        return [
            'assignedTargets' => $targetIds->count() + $officeTargetIds->count(),
            'submittedAccomplishments' => Accomplishment::where('submitted_by', $user->id)->count()
                + DB::query()->fromSub((clone $movQuery)->select('office_target_id', 'user_id')->distinct(), 'submissions')->count(),
            'accomplishedTargets' => $completed,
            'progress' => [
                'Completed' => $completed,
                'In progress' => $inProgress,
                'Not started' => $notStarted,
            ],
            'employeeProgress' => $user->hasAnyRole(['Campus Director', 'Supervisor', 'HR Head'])
                ? $this->employeeProgress($user)
                : collect(),
        ];
    }

    private function employeeProgress(User $viewer): Collection
    {
        $employees = User::query()
            ->with(['office', 'positionTitle'])
            ->whereHas('roles', fn ($query) => $query->where('name', 'Employee'));

        $campus = $viewer->office?->campus;
        if ($viewer->hasRole('Campus Director')) {
            $employees->where(function ($query) use ($viewer, $campus) {
                if ($campus !== null) {
                    $query->whereHas('office', fn ($office) => $office->where('campus', $campus));
                }
                $query->orWhereIn('users.id', DB::table('office_target_employee')
                    ->join('office_targets', 'office_targets.id', '=', 'office_target_employee.office_target_id')
                    ->where('office_targets.created_by', $viewer->id)->select('user_id'));
            });
        }
        if ($viewer->hasAnyRole(['Supervisor', 'HR Head'])) {
            $employees->where('office_id', $viewer->office_id ?? 0);
        }

        $employees = $employees->orderBy('name')->get();
        if ($employees->isEmpty()) {
            return collect();
        }

        $employeeIds = $employees->modelKeys();
        $assignments = DB::table('office_target_employee')
            ->join('office_targets', 'office_targets.id', '=', 'office_target_employee.office_target_id')
            ->whereIn('user_id', $employeeIds)
            ->get(['user_id', 'office_target_id', 'completed_at', 'office_targets.created_by'])
            ->groupBy('user_id');
        $submittedTargets = TargetMov::whereIn('user_id', $employeeIds)
            ->select('user_id', 'office_target_id')->distinct()->get()->groupBy('user_id');

        $reportEmployeeIds = $employees->filter(fn ($employee) => ! $viewer->hasRole('Campus Director')
            || ($campus !== null && $employee->office?->campus === $campus))->modelKeys();
        $reportTargets = null;
        foreach ([Dpcr::class, Upcr::class, Ipcr::class] as $model) {
            $forms = $model::whereIn('employee_id', $reportEmployeeIds)->select('id', 'employee_id');
            $query = PerformanceTarget::query()
                ->joinSub($forms, 'forms', 'forms.id', '=', 'performance_targets.targetable_id')
                ->where('targetable_type', $model)
                ->select('performance_targets.id', 'forms.employee_id')->toBase();
            $reportTargets = $reportTargets ? $reportTargets->unionAll($query) : $query;
        }
        $reportTargets = $reportTargets->get();
        $allProgress = Accomplishment::whereIn('performance_target_id', $reportTargets->pluck('id'))
            ->selectRaw('performance_target_id, MAX(completion_percentage) as progress')
            ->groupBy('performance_target_id')->pluck('progress', 'performance_target_id');
        $reportTargets = $reportTargets->groupBy('employee_id');

        return $employees->map(function (User $employee) use ($viewer, $campus, $assignments, $submittedTargets, $reportTargets, $allProgress): array {
            $employeeAssignments = $assignments->get($employee->id, collect());
            if ($viewer->hasRole('Campus Director') && ($campus === null || $employee->office?->campus !== $campus)) {
                $employeeAssignments = $employeeAssignments->where('created_by', $viewer->id);
            }
            $officeTargetIds = $employeeAssignments->pluck('office_target_id')->unique();
            $submitted = $submittedTargets->get($employee->id, collect())->whereIn('office_target_id', $officeTargetIds)->count();
            $officeCompleted = $employeeAssignments->whereNotNull('completed_at')->count();
            $targetIds = $reportTargets->get($employee->id, collect())->pluck('id');
            $progress = $allProgress->only($targetIds->all());

            $completed = $progress->filter(fn ($value) => (float) $value >= 100)->count();
            $average = $targetIds->isEmpty()
                ? 0
                : round($targetIds->sum(fn ($id) => (float) ($progress[$id] ?? 0)) / $targetIds->count(), 1);

            return [
                'name' => $employee->name,
                'office' => $employee->office?->name ?? 'Unassigned Office',
                'position' => $employee->positionTitle?->title ?? 'Unassigned Position',
                'targets' => $targetIds->count() + $officeTargetIds->count(),
                'officeTargets' => $officeTargetIds->count(),
                'reportTargets' => $targetIds->count(),
                'submitted' => $submitted,
                'completed' => $completed + $officeCompleted,
                'average' => $average,
            ];
        });
    }
}
