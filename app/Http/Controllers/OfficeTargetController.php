<?php

namespace App\Http\Controllers;

use App\Models\EvaluationCycle;
use App\Models\Ipcr;
use App\Models\Office;
use App\Models\OfficeTarget;
use App\Models\PerformanceTarget;
use App\Models\StrategicObjective;
use App\Models\User;
use App\Models\WorkflowAudit;
use App\Notifications\EmployeeTargetAssignedNotification;
use App\Services\CommitmentPlanning;
use App\Services\PlanningOrganization;
use App\Services\TargetDocumentAi;
use App\Services\TargetHierarchy;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;
use Illuminate\View\View;
use Symfony\Component\HttpFoundation\StreamedResponse;

class OfficeTargetController extends Controller
{
    public function index(Request $request): View
    {
        $query = OfficeTarget::with(['evaluationCycle', 'offices', 'createdBy'])->latest();
        if (! $request->user()->hasAnyRole(['Administrator', 'Planning Officer', 'Campus Director'])) {
            $managed = app(TargetHierarchy::class)->managedOfficeIds($request->user());
            $query->where(fn ($q) => $q->whereHas('offices', fn ($office) => $office->whereIn('offices.id', $managed))
                ->orWhereHas('assignedEmployees', fn ($person) => $person->whereKey($request->user()->id)));
        }

        $officeIds = app(PlanningOrganization::class)->accessibleOfficeIds($request->user());
        $targets = $query->paginate(10)->through(function ($target) use ($officeIds) {
            if ($target->evaluationCycle?->multi_campus_enabled) {
                $target->setRelation('offices', $target->offices->whereIn('id', $officeIds)->values());
            }

            return $target;
        });

        return view('office-targets.index', compact('targets'));
    }

    public function create(Request $request): RedirectResponse
    {
        abort_unless($request->user()->hasAnyRole(['Administrator', 'Planning Officer', 'Campus Director']), 403);

        return redirect()->route('target-documents.index');
    }

    public function store(Request $request): RedirectResponse
    {
        CommitmentPlanning::legacyOnly(EvaluationCycle::find($request->input('evaluation_cycle_id')));
        abort_unless($request->user()->hasAnyRole(['Administrator', 'Planning Officer', 'Campus Director']), 403);
        $data = $request->validate([
            'evaluation_cycle_id' => [
                'required',
                Rule::exists('evaluation_cycles', 'id')->where('status', EvaluationCycle::STATUS_OPEN),
            ],
            'strategic_objective_id' => ['nullable', 'integer', Rule::exists('strategic_objectives', 'id')],
            'title' => ['required', 'string', 'max:255'], 'description' => ['nullable', 'string', 'max:5000'],
            'success_indicator' => ['required', 'string', 'max:5000'], 'target_value' => ['required', 'numeric', 'gt:0'],
            'unit_of_measure' => ['required', 'string', 'max:100'], 'weight' => ['required', 'numeric', 'min:0.01', 'max:100'],
            'start_date' => ['nullable', 'date'], 'deadline' => ['required', 'date', 'after_or_equal:start_date'],
            'priority' => ['required', Rule::in(OfficeTarget::PRIORITIES)], 'instructions' => ['nullable', 'string', 'max:5000'],
            'office_ids' => ['required', 'array', 'min:1'], 'office_ids.*' => ['integer', Rule::exists('offices', 'id')->where('is_active', true)->where('campus', config('hinunangan.campus_id'))],
            'office_allocations' => ['required', 'array'], 'office_allocations.*' => ['required', 'numeric', 'gt:0'],
            'file' => ['nullable', 'file', 'mimes:pdf,doc,docx,xls,xlsx,jpg,jpeg,png', 'max:20480'],
        ]);
        EvaluationCycle::findOrFail($data['evaluation_cycle_id'])->validateSchedule($data);
        $officeIds = array_values(array_unique($data['office_ids']));
        unset($data['office_ids']);
        $officeAllocations = $this->validatedOfficeAllocations($officeIds, $data['office_allocations'], (float) $data['target_value']);
        unset($data['office_allocations']);
        if (! empty($data['strategic_objective_id'])) {
            $objective = $this->authorizedStrategicObjective($request, (int) $data['strategic_objective_id']);
            $data['strategic_goal'] = $objective->strategicGoal->title;
            $data['strategic_goal_description'] = $objective->strategicGoal->description;
            $data['strategic_objective'] = $objective->title;
        }
        $file = $data['file'] ?? null;
        unset($data['file']);
        $briefings = $this->prepareBriefings($data, $officeAllocations);
        $target = DB::transaction(function () use ($data, $file, $request, $officeAllocations, $officeIds, $briefings) {
            $cycle = EvaluationCycle::whereKey($data['evaluation_cycle_id'])->lockForUpdate()->firstOrFail();
            $cycle->validateSchedule($data);
            $target = OfficeTarget::create([...$data, 'file_path' => $file?->store('office-targets'), 'original_name' => $file?->getClientOriginalName(), 'created_by' => $request->user()->id]);
            $target->offices()->sync($this->withBriefings($officeAllocations, $briefings));
            app(TargetHierarchy::class)->linkAvailableUpcrs($target);
            WorkflowAudit::record($target, 'Office target created and allocated', null, $this->snapshot($target));
            $target->notifyAssignedSupervisors($officeIds);

            return $target;
        });

        return redirect()->route('office-targets.show', $target)->with('status', 'Target assigned to the selected offices.');
    }

    public function show(Request $request, OfficeTarget $officeTarget): View|RedirectResponse
    {
        if ($officeTarget->performance_target_id) {
            $plans = PerformanceTarget::where('root_target_id', $officeTarget->performance_target_id)->with('plan')->get()->pluck('plan')->filter()->unique('id');
            $plan = $plans->first(fn ($p) => app(PlanningOrganization::class)->canView($request->user(), $p));
            abort_unless($plan, 403);

            return redirect()->route('planning.show', $plan);
        }
        $this->authorizeAccess($request, $officeTarget);

        return view('office-targets.show', ['target' => $officeTarget->load(['evaluationCycle', 'offices', 'createdBy'])]);
    }

    public function edit(Request $request, OfficeTarget $officeTarget): View
    {
        $this->authorizeManager($request, $officeTarget);
        $offices = Office::forCampus()->with('targetHeads.roles')->orderBy('name')->orderBy('campus')->orderBy('id');

        return view('office-targets.create', [
            'offices' => $offices->get(),
            'cycles' => EvaluationCycle::where('status', EvaluationCycle::STATUS_OPEN)->orderByDesc('year')->get(),
            'target' => $officeTarget->load('offices'),
        ]);
    }

    public function update(Request $request, OfficeTarget $officeTarget): RedirectResponse
    {
        CommitmentPlanning::legacyOnly($officeTarget->evaluationCycle);
        CommitmentPlanning::legacyOnly(EvaluationCycle::find($request->input('evaluation_cycle_id')));
        $this->authorizeManager($request, $officeTarget);
        $data = $request->validate([
            'evaluation_cycle_id' => ['required', Rule::exists('evaluation_cycles', 'id')->where('status', EvaluationCycle::STATUS_OPEN)],
            'strategic_objective_id' => ['nullable', 'integer', Rule::exists('strategic_objectives', 'id')],
            'title' => ['required', 'string', 'max:255'], 'description' => ['nullable', 'string', 'max:5000'],
            'success_indicator' => ['required', 'string', 'max:5000'], 'target_value' => ['required', 'numeric', 'gt:0'],
            'unit_of_measure' => ['required', 'string', 'max:100'], 'weight' => ['required', 'numeric', 'min:0.01', 'max:100'],
            'start_date' => ['nullable', 'date'], 'deadline' => ['required', 'date', 'after_or_equal:start_date'],
            'priority' => ['required', Rule::in(OfficeTarget::PRIORITIES)], 'instructions' => ['nullable', 'string', 'max:5000'],
            'office_ids' => ['required', 'array', 'min:1'], 'office_ids.*' => ['integer', Rule::exists('offices', 'id')->where('is_active', true)->where('campus', config('hinunangan.campus_id'))],
            'office_allocations' => ['required', 'array'], 'office_allocations.*' => ['required', 'numeric', 'gt:0'],
            'file' => ['nullable', 'file', 'mimes:pdf,doc,docx,xls,xlsx,jpg,jpeg,png', 'max:20480'],
        ]);
        if ($officeTarget->evaluation_cycle_id && (int) $data['evaluation_cycle_id'] !== (int) $officeTarget->evaluation_cycle_id) {
            throw ValidationException::withMessages(['evaluation_cycle_id' => 'Keep the target in its referenced evaluation period.']);
        }
        EvaluationCycle::findOrFail($data['evaluation_cycle_id'])->validateSchedule($data);
        $officeIds = array_values(array_unique($data['office_ids']));
        unset($data['office_ids']);
        $officeAllocations = $this->validatedOfficeAllocations($officeIds, $data['office_allocations'], (float) $data['target_value']);
        unset($data['office_allocations']);
        if (! empty($data['strategic_objective_id'])) {
            $objective = $this->authorizedStrategicObjective($request, (int) $data['strategic_objective_id']);
            $data['strategic_goal'] = $objective->strategicGoal->title;
            $data['strategic_goal_description'] = $objective->strategicGoal->description;
            $data['strategic_objective'] = $objective->title;
        }
        $file = $data['file'] ?? null;
        unset($data['file']);
        $briefings = $this->prepareBriefings($data, $officeAllocations, $officeTarget->offices()->whereNotNull('ai_briefing')->exists());
        $previousPath = $officeTarget->file_path;
        if ($file) {
            $data['file_path'] = $file->store('office-targets');
            $data['original_name'] = $file->getClientOriginalName();
        }
        try {
            DB::transaction(function () use ($officeTarget, $data, $officeAllocations, $briefings, $officeIds) {
                $locked = OfficeTarget::whereKey($officeTarget->id)->lockForUpdate()->firstOrFail();
                $cycle = EvaluationCycle::whereKey($data['evaluation_cycle_id'])->lockForUpdate()->firstOrFail();
                $cycle->validateSchedule($data);
                $locked->evaluationCycle->validateSchedule([]);
                $before = $this->snapshot($locked);
                foreach ($locked->assignedEmployees()->get()->groupBy('office_id') as $officeId => $people) {
                    $newLimit = $officeAllocations[$officeId]['allocated_target_value'] ?? null;
                    if ($newLimit === null || $people->contains(fn ($person) => $person->pivot->allocated_target_value === null)
                        || $people->sum(fn ($person) => (float) $person->pivot->allocated_target_value) > (float) $newLimit + 0.005) {
                        throw ValidationException::withMessages(['office_allocations' => 'Reconcile employee assignments before removing an office or reducing its allocated quantity.']);
                    }
                }
                $locked->update($data);
                $locked->offices()->sync($this->withBriefings($officeAllocations, $briefings));
                app(TargetHierarchy::class)->linkAvailableUpcrs($locked);
                WorkflowAudit::record($locked, 'CD edited target details and office allocations', $before, $this->snapshot($locked));
                $locked->notifyAssignedSupervisors($officeIds, true);
            });
        } catch (\Throwable $exception) {
            if ($file) {
                Storage::disk('local')->delete($data['file_path']);
            }
            throw $exception;
        }
        if ($file && $previousPath) {
            Storage::disk('local')->delete($previousPath);
        }

        return redirect()->route('office-targets.show', $officeTarget)->with('status', 'Office target updated successfully.');
    }

    public function download(Request $request, OfficeTarget $officeTarget): StreamedResponse
    {
        $this->authorizeAccess($request, $officeTarget);
        abort_unless($officeTarget->file_path, 404);

        return Storage::disk('local')->download($officeTarget->file_path, $officeTarget->original_name);
    }

    private function authorizeAccess(Request $request, OfficeTarget $target): void
    {
        abort_unless(app(TargetHierarchy::class)->canView($request->user(), $target), 403);
    }

    public function assignEmployees(Request $request, OfficeTarget $officeTarget): RedirectResponse
    {
        CommitmentPlanning::legacyOnly($officeTarget->evaluationCycle);
        abort_unless($request->user()->hasAnyRole(['Supervisor', 'Division Head', 'Unit Head', 'HR Head']) && $request->user()->office_id, 403);
        $this->authorizeAccess($request, $officeTarget);
        $data = $request->validate([
            'employee_ids' => ['required', 'array', 'min:1'],
            'employee_ids.*' => ['required', 'integer', 'distinct'],
            'allocations' => ['required', 'array'],
            'allocations.*' => ['required', 'numeric', 'gt:0', 'decimal:0,2'],
        ]);
        $employees = User::where('office_id', $request->user()->office_id)
            ->where(fn ($q) => $q->whereKey($request->user()->id)
                ->orWhereHas('roles', fn ($roles) => $roles->where('name', 'Employee')))
            ->whereIn('id', $data['employee_ids'])->get();
        abort_unless($employees->count() === count($data['employee_ids']), 403);
        $ids = array_map('intval', $data['employee_ids']);
        $keys = array_map('intval', array_keys($data['allocations']));
        sort($ids);
        sort($keys);
        if ($ids !== $keys) {
            throw ValidationException::withMessages(['allocations' => 'Enter an allocation for each selected person.']);
        }
        DB::transaction(function () use ($employees, $officeTarget, $request, $data) {
            OfficeTarget::whereKey($officeTarget->id)->lockForUpdate()->firstOrFail();
            $office = $officeTarget->offices()->whereKey($request->user()->office_id)->firstOrFail();
            $limit = $office->pivot->allocated_target_value;
            if ($limit === null) {
                throw ValidationException::withMessages(['allocations' => 'Ask the target creator to set this office allocation first.']);
            }
            $existing = $officeTarget->assignedEmployees()->where('users.office_id', $office->id)->get();
            $total = 0;
            foreach ($existing as $person) {
                if (array_key_exists($person->id, $data['allocations'])) {
                    continue;
                }
                if ($person->pivot->allocated_target_value === null) {
                    throw ValidationException::withMessages(['allocations' => 'Include existing assignees and specify their allocations.']);
                }
                $total += (int) round($person->pivot->allocated_target_value * 100);
            }
            foreach ($data['allocations'] as $value) {
                $total += (int) round($value * 100);
            }
            if ($total > (int) round($limit * 100)) {
                throw ValidationException::withMessages(['allocations' => 'Combined assignments cannot exceed the office allocation of '.$limit.' '.$officeTarget->unit_of_measure.'.']);
            }
            foreach ($employees as $employee) {
                $current = $existing->firstWhere('id', $employee->id);
                $allocation = $data['allocations'][$employee->id];
                if ($current && (float) $allocation < (float) $current->pivot->completed_value) {
                    throw ValidationException::withMessages(['allocations' => 'An allocation cannot be reduced below the quantity already reported complete.']);
                }
                if ($current?->pivot->completed_at && (float) $current->pivot->allocated_target_value !== (float) $allocation) {
                    throw ValidationException::withMessages(['allocations' => 'Completed assignments cannot be reallocated.']);
                }
                $pivot = ['allocated_target_value' => $allocation];
                if (! $current) {
                    $pivot['assigned_by'] = $request->user()->id;
                    if ($employee->id === $request->user()->id) {
                        $pivot['accepted_at'] = now();
                    }
                }
                $changes = $officeTarget->assignedEmployees()->syncWithoutDetaching([$employee->id => $pivot]);
                if ($changes['attached'] && $employee->id !== $request->user()->id) {
                    $employee->notify(new EmployeeTargetAssignedNotification($officeTarget, $request->user()->name));
                }
            }
        });

        return back()->with('status', 'Target assigned to the selected employees.');
    }

    public function linkUpcr(Request $request, OfficeTarget $officeTarget): RedirectResponse
    {
        $this->authorizeAccess($request, $officeTarget);
        $data = $request->validate(['office_id' => ['required', 'integer'], 'upcr_id' => ['required', 'integer']]);
        $hierarchy = app(TargetHierarchy::class);
        abort_unless($request->user()->hasAnyRole(['Administrator', 'Planning Officer']) || in_array($data['office_id'], $hierarchy->managedOfficeIds($request->user())), 403);
        DB::transaction(function () use ($officeTarget, $data, $hierarchy) {
            OfficeTarget::whereKey($officeTarget->id)->lockForUpdate()->firstOrFail();
            abort_unless($officeTarget->evaluationCycle?->isOpen(), 422, 'The evaluation period is closed.');
            $office = $officeTarget->offices()->whereKey($data['office_id'])->firstOrFail();
            $upcr = $hierarchy->eligibleUpcrs($officeTarget, $office)->whereKey($data['upcr_id'])->first();
            if (! $upcr) {
                throw ValidationException::withMessages(['upcr_id' => 'Choose a UPCR belonging to this office and evaluation period within its reporting hierarchy.']);
            }
            $existing = Ipcr::where('office_target_id', $officeTarget->id)->whereHas('employee', fn ($q) => $q->where('office_id', $office->id))->get();
            abort_if($existing->contains(fn ($ipcr) => $ipcr->upcr_id && (int) $ipcr->upcr_id !== $upcr->id), 422, 'An IPCR already references a different UPCR. Preserve that report history.');
            $officeTarget->offices()->updateExistingPivot($office->id, ['upcr_id' => $upcr->id]);
            foreach ($existing as $ipcr) {
                if (! $ipcr->upcr_id && in_array($ipcr->status, ['draft', 'returned'])) {
                    $ipcr->update(['upcr_id' => $upcr->id]);
                }
            }
            WorkflowAudit::record($officeTarget, 'Office UPCR hierarchy linked', ['office_id' => $office->id, 'upcr_id' => $office->pivot->upcr_id], ['office_id' => $office->id, 'upcr_id' => $upcr->id]);
        });

        return back()->with('status', 'Target linked to the office UPCR. Individual assignments remain under the same office hierarchy.');
    }

    private function authorizeManager(Request $request, OfficeTarget $target): void
    {
        abort_unless($request->user()->hasRole('Administrator') || $target->created_by === $request->user()->id, 403);
    }

    public function takeTarget(Request $request, OfficeTarget $officeTarget): RedirectResponse
    {
        $request->merge([
            'employee_ids' => [$request->user()->id],
            'allocations' => [$request->user()->id => $request->input('allocation')],
        ]);

        return $this->assignEmployees($request, $officeTarget);
    }

    public function acceptTarget(Request $request, OfficeTarget $officeTarget): RedirectResponse
    {
        $this->authorizeAccess($request, $officeTarget);
        abort_unless($officeTarget->assignedEmployees()->whereKey($request->user()->id)->exists() && $officeTarget->offices()->whereKey($request->user()->office_id)->exists(), 403);
        DB::table('office_target_employee')->where('office_target_id', $officeTarget->id)->where('user_id', $request->user()->id)->whereNull('accepted_at')->update(['accepted_at' => now(), 'updated_at' => now()]);

        return back()->with('mov_status', 'Assignment accepted. You can now submit your accomplishment and MOV.');
    }

    private function validatedOfficeAllocations(array $officeIds, array $allocations, float $totalTarget): array
    {
        $officeIds = array_map('intval', $officeIds);
        $allocationOfficeIds = array_map('intval', array_keys($allocations));
        sort($officeIds);
        sort($allocationOfficeIds);

        if ($officeIds !== $allocationOfficeIds) {
            throw ValidationException::withMessages([
                'office_allocations' => 'Each selected office must have one target allocation.',
            ]);
        }

        $allocatedTotal = array_sum(array_map('floatval', $allocations));
        if (abs($allocatedTotal - $totalTarget) > 0.005) {
            throw ValidationException::withMessages([
                'office_allocations' => 'Office allocations must equal the total target value of '.number_format($totalTarget, 2).'.',
            ]);
        }

        return collect($allocations)->mapWithKeys(fn ($value, $officeId) => [
            (int) $officeId => ['allocated_target_value' => $value],
        ])->all();
    }

    private function prepareBriefings(array $data, array $allocations, bool $required = false): array
    {
        if (! config('spms.ai.enabled') && ! $required) {
            return [];
        }
        set_time_limit(180);
        try {
            return app(TargetDocumentAi::class)->briefings($data, collect($allocations)->map(fn ($pivot) => $pivot['allocated_target_value'])->all());
        } catch (\RuntimeException $exception) {
            throw ValidationException::withMessages(['analysis' => $exception->getMessage()]);
        }
    }

    private function withBriefings(array $allocations, array $briefings): array
    {
        foreach ($allocations as $officeId => &$pivot) {
            $pivot['ai_briefing'] = isset($briefings[$officeId]) ? json_encode($briefings[$officeId], JSON_THROW_ON_ERROR) : null;
        }

        return $allocations;
    }

    private function snapshot(OfficeTarget $target): array
    {
        return ['target' => $target->fresh()->toArray(), 'offices' => $target->offices()->get()->map(fn ($office) => [
            'office_id' => $office->id, 'office_name' => $office->name,
            'quantity' => $office->pivot->allocated_target_value,
            'allocation_percentage' => (float) $target->target_value > 0 ? round($office->pivot->allocated_target_value / $target->target_value * 100, 2) : null,
            'briefing' => json_decode($office->pivot->ai_briefing ?? 'null', true),
        ])->all()];
    }

    private function authorizedStrategicObjective(Request $request, int $objectiveId): StrategicObjective
    {
        $objective = StrategicObjective::with('strategicGoal')->findOrFail($objectiveId);
        abort_unless($request->user()->hasRole('Administrator') || $objective->strategicGoal->created_by === $request->user()->id, 403);

        return $objective;
    }
}
