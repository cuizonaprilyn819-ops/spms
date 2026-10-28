<?php

namespace App\Services;

use App\Models\CommitmentPlan;
use App\Models\Dpcr;
use App\Models\EvaluationCycle;
use App\Models\Ipcr;
use App\Models\Office;
use App\Models\OfficeTarget;
use App\Models\Opcr;
use App\Models\PerformanceTarget;
use App\Models\PlanningEvent;
use App\Models\PlanningOutput;
use App\Models\Upcr;
use App\Models\User;
use App\Notifications\PlanningNotification;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class CommitmentPlanning
{
    public const REPORTS = ['opcr' => Opcr::class, 'dpcr' => Dpcr::class, 'upcr' => Upcr::class, 'ipcr' => Ipcr::class];

    public function __construct(public PlanningOrganization $organization, public TargetAllocation $allocation) {}

    private function fail(string $message): never
    {
        throw ValidationException::withMessages(['planning' => $message]);
    }

    public function lockRoot(CommitmentPlan $plan): void
    {
        while ($plan->parent_id) {
            $plan = $plan->parent;
        }
        CommitmentPlan::whereKey($plan->id)->lockForUpdate()->firstOrFail();
    }

    public static function legacyOnly(?EvaluationCycle $cycle): void
    {
        abort_if($cycle?->planning_enabled, 422, 'This period uses Commitment Planning. Create and cascade commitments there.');
    }

    public function event(?CommitmentPlan $plan, User $actor, string $action, array $data = []): void
    {
        PlanningEvent::create(['commitment_plan_id' => $plan?->id, 'actor_id' => $actor->id, 'action' => $action, 'data' => $data]);
    }

    public function writable(CommitmentPlan $plan): void
    {
        if (! $plan->cycle->planning_enabled || ! $plan->cycle->isOpen()) {
            $this->fail('This planning period is not open.');
        }
        if ($plan->state !== 'draft') {
            $this->fail('Return or amend this plan before changing its commitments.');
        }
    }

    public function createPlan(User $actor, EvaluationCycle $cycle, Office $office, string $level, ?CommitmentPlan $parent, ?User $employee = null, ?string $reason = null): CommitmentPlan
    {
        return DB::transaction(function () use ($actor, $cycle, $office, $level, $parent, $employee, $reason) {
            $cycle = EvaluationCycle::whereKey($cycle->id)->lockForUpdate()->firstOrFail();
            if (! $cycle->planning_enabled || ! $cycle->isOpen()) {
                $this->fail('Choose an open period with commitment planning enabled.');
            }
            $this->organization->verify($office);
            if ($level !== 'ipcr' && $employee) {
                $this->fail('Choose a person only for an IPCR; organizational plans belong to their office.');
            }
            abort_unless($this->organization->isOwner($actor, $office) || ($level === 'ipcr' && $employee?->is($actor)), 403);
            $reviewerId = $office->planning_reviewer_id;
            if ($reviewerId === $actor->id || ($employee && $reviewerId === $employee->id)) {
                $this->fail('Assign a reviewer who is separate from the preparer and ratee.');
            }
            if ($level === 'opcr') {
                if ($parent || $office->planning_level !== 'office' || $employee) {
                    $this->fail('An OPCR belongs to a verified top-level office.');
                }
            } else {
                if (! $parent || $parent->evaluation_cycle_id !== $cycle->id) {
                    $this->fail('Select the parent commitment from the same period.');
                }
                $expected = ['dpcr' => ['opcr', 'division'], 'upcr' => ['dpcr', 'unit']];
                if (isset($expected[$level]) && ($parent->level !== $expected[$level][0] || $office->planning_level !== $expected[$level][1] || (int) $office->parent_office_id !== $parent->office_id)) {
                    $this->fail('The selected report does not follow this office’s reporting structure.');
                }
                if ($level === 'ipcr') {
                    if (! $employee || (int) $employee->office_id !== $office->id || ! trim($employee->planning_duties ?? '')) {
                        $this->fail('Record the employee’s office and verified duties first.');
                    }
                    $ordinary = $parent->level === 'upcr' && $parent->office_id === $office->id;
                    $head = $office->head_id === $employee->id && $parent->office_id === $office->id && $parent->level === ['office' => 'opcr', 'division' => 'dpcr', 'unit' => 'upcr'][$office->planning_level];
                    if (! $ordinary && (! $head || ! trim($reason ?? ''))) {
                        $this->fail('An employee needs their unit UPCR; a head’s actual-level IPCR needs an explanation.');
                    }
                }
            }
            $existing = CommitmentPlan::where('evaluation_cycle_id', $cycle->id)->where('office_id', $office->id)->where('level', $level)->where('employee_id', $employee?->id)->first();
            if ($existing) {
                return $existing;
            }
            $model = self::REPORTS[$level] ?? abort(422);
            $data = ['title' => strtoupper($level).' · '.$office->name.($employee ? ' · '.$employee->name : ''), 'prepared_by' => $actor->id, 'status' => 'draft'];
            if ($level === 'opcr') {
                $data += ['office_id' => $office->id, 'evaluation_cycle_id' => $cycle->id];
            }
            if ($level === 'dpcr') {
                $data += ['opcr_id' => $parent->report_id, 'office_id' => $office->id, 'employee_id' => $office->head_id];
            }
            if ($level === 'upcr') {
                $data += ['dpcr_id' => $parent->report_id, 'office_id' => $office->id, 'employee_id' => $office->head_id];
            }
            if ($level === 'ipcr') {
                $data += ['upcr_id' => $parent->level === 'upcr' ? $parent->report_id : null, 'employee_id' => $employee->id];
            }
            $report = new $model;
            $report->forceFill($data)->save();
            $plan = CommitmentPlan::create(['report_type' => $model, 'report_id' => $report->id, 'evaluation_cycle_id' => $cycle->id, 'office_id' => $office->id, 'employee_id' => $employee?->id, 'parent_id' => $parent?->id, 'prepared_by' => $actor->id, 'reviewer_id' => $reviewerId, 'level' => $level, 'head_exception_reason' => $reason]);
            $this->event($plan, $actor, 'Plan created');

            return $plan;
        });
    }

    public function addTarget(CommitmentPlan $plan, User $actor, array $data, ?PerformanceTarget $parent = null): PerformanceTarget
    {
        abort_unless($this->organization->canPrepare($actor, $plan), 403);

        return DB::transaction(function () use ($plan, $actor, $data, $parent) {
            $this->lockRoot($plan);
            $plan = CommitmentPlan::whereKey($plan->id)->lockForUpdate()->firstOrFail();
            $this->writable($plan);
            if ($plan->level !== 'opcr' && ! $parent) {
                $this->fail('Select a relevant target from the parent plan.');
            }
            if (! $parent && $plan->targets()->where('target', $data['target'])->where('success_indicator', $data['success_indicator'])->where('source_evidence', $data['source_evidence'])->exists()) {
                $this->fail('This source target is already in the OPCR draft.');
            }
            if ($parent) {
                if ($parent->commitment_plan_id !== $plan->parent_id || $parent->plan_revision !== $plan->parent->revision) {
                    $this->fail('Select a target from the current parent revision.');
                }
                if (! $this->eligible($parent, $plan)) {
                    $this->fail('The parent head must confirm this target’s relevance to the office or employee first.');
                }
                if ($plan->targets()->where('root_target_id', $parent->root_target_id ?: $parent->id)->exists()) {
                    $this->fail('This target is already included. Amend the draft rather than fetching it twice.');
                }
                foreach (['measurement', 'unit', 'whole_numbers', 'standards', 'deadline', 'source_evidence', 'success_indicator'] as $key) {
                    $data[$key] = $parent->$key;
                }
                $data['source_revision'] = $parent->plan->revision;
                $data['parent_target_id'] = $parent->id;
                $data['root_target_id'] = $parent->root_target_id ?: $parent->id;
                if ($parent->measurement === 'rate') {
                    $data['quantity'] = $parent->quantity;
                }
                if ($parent->measurement === 'shared') {
                    $data['quantity'] = 1;
                }
                if (! empty($data['contributes_only'])) {
                    if ($parent->measurement !== 'shared') {
                        $this->fail('Contribution-only targets are for shared outputs.');
                    }
                    $data['quantity'] = 0;
                }
            }
            if (($data['whole_numbers'] ?? true) && $data['measurement'] === 'count' && floor((float) $data['quantity']) !== (float) $data['quantity']) {
                $this->fail('Enter a whole number for this output.');
            }
            if ($data['measurement'] === 'rate' && (float) $data['quantity'] > 100) {
                $this->fail('A percentage standard cannot exceed 100%.');
            }
            if ($data['measurement'] === 'shared' && ! $parent) {
                $data['quantity'] = 1;
            }
            $target = $plan->report->performanceTargets()->create([...$data, 'commitment_plan_id' => $plan->id, 'plan_revision' => $plan->revision, 'created_by' => $actor->id, 'weight' => $data['weight'] ?? 0]);
            if (! $parent) {
                $target->update(['root_target_id' => $target->id]);
            }
            $this->event($plan, $actor, 'Target drafted', ['target_id' => $target->id]);
            $plan->update(['agreed_at' => null]);

            return $target;
        });
    }

    public function eligible(PerformanceTarget $source, CommitmentPlan $destination): bool
    {
        $q = DB::table('target_eligibilities')->where('performance_target_id', $source->id)->where('office_id', $destination->office_id);

        return $destination->level === 'ipcr' ? $q->where('employee_id', $destination->employee_id)->exists() : $q->whereNull('employee_id')->exists();
    }

    public function used(PerformanceTarget $parent, ?int $exceptPlan = null): float
    {
        $sourceIds = [$parent->id];
        $previous = $parent;
        while ($previous->supersedes_id) {
            $previous = PerformanceTarget::findOrFail($previous->supersedes_id);
            $sourceIds[] = $previous->id;
        }

        return (float) PerformanceTarget::whereIn('parent_target_id', $sourceIds)
            ->where('contributes_only', false)
            ->whereHas('plan', fn ($q) => $q->whereNotNull('approved_revision')->when($exceptPlan, fn ($q) => $q->where('id', '!=', $exceptPlan)))
            ->get()->filter(fn ($t) => $t->plan_revision === $t->plan->approved_revision)->sum('quantity');
    }

    public function suggestion(PerformanceTarget $parent): array
    {
        $rows = DB::table('planning_drivers')->where('performance_target_id', $parent->id)->get();
        $drivers = [];
        foreach ($rows as $d) {
            $drivers[$d->id] = ['value' => (float) $d->value, 'capacity' => $d->capacity === null ? null : (float) $d->capacity, 'office_id' => $d->office_id, 'employee_id' => $d->employee_id, 'name' => $d->name, 'source' => $d->source, 'valid_until' => $d->valid_until];
        }
        if ($parent->measurement !== 'count') {
            return ['drivers' => $drivers, 'shares' => [], 'unallocated' => null];
        }
        $eligible = DB::table('target_eligibilities')->where('performance_target_id', $parent->id)->get();
        $missing = $eligible->contains(fn ($e) => ! $rows->contains(fn ($r) => $r->office_id === $e->office_id && $r->employee_id === $e->employee_id && $r->valid_until >= today()->toDateString()));
        if ($missing || $rows->contains(fn ($r) => $r->valid_until < today()->toDateString())) {
            return ['drivers' => $drivers, 'shares' => [], 'unallocated' => $parent->quantity, 'requires_manual' => 'Record current inputs for every eligible recipient, or enter quantities manually.'];
        }

        return [...$this->allocation->suggest((float) $parent->quantity, $drivers, $parent->whole_numbers), 'drivers' => $drivers];
    }

    private function reviewedShares(PerformanceTarget $source, ?int $exceptPlan = null): float
    {
        $ids = [$source->id];
        $old = $source;
        while ($old->supersedes_id) {
            $old = PerformanceTarget::findOrFail($old->supersedes_id);
            $ids[] = $old->id;
        }

        return PerformanceTarget::whereIn('parent_target_id', $ids)->with('plan')->get()->groupBy('commitment_plan_id')->sum(function ($lines) use ($source, $exceptPlan) {
            $plan = $lines->first()->plan;
            if ($plan->id === $exceptPlan) {
                return 0;
            }
            $reviewed = $plan->state === 'endorsed' ? $lines->first(fn ($t) => $t->plan_revision === $plan->revision && $t->parent_target_id === $source->id && ! $t->needs_reconciliation) : null;
            $effective = $reviewed ?? $lines->first(fn ($t) => $t->plan_revision === $plan->approved_revision);

            return $effective && ! $effective->contributes_only ? (float) $effective->quantity : 0;
        });
    }

    public function submit(CommitmentPlan $plan, User $actor): void
    {
        abort_unless($this->organization->canPrepare($actor, $plan), 403);
        DB::transaction(function () use ($plan, $actor) {
            $this->lockRoot($plan);
            $plan = CommitmentPlan::whereKey($plan->id)->lockForUpdate()->firstOrFail();
            $this->writable($plan);
            if (! $plan->targets()->exists() || $plan->targets()->where('needs_reconciliation', true)->exists()) {
                $this->fail('Add and reconcile the plan’s target lines before submitting.');
            }
            $plan->update(['state' => 'submitted']);
            $this->event($plan, $actor, 'Commitments submitted');
            $plan->reviewer->notify(new PlanningNotification($plan, 'Commitments are ready for your review.'));
        });
    }

    public function approve(CommitmentPlan $plan, User $actor, array $external = []): void
    {
        abort_unless($actor->id === $plan->reviewer_id && $actor->id !== $plan->prepared_by && $actor->id !== $plan->employee_id, 403);
        DB::transaction(function () use ($plan, $actor, $external) {
            $this->lockRoot($plan);
            // All siblings acquire the same parent lock before reserving quantities.
            if ($plan->parent_id) {
                CommitmentPlan::whereKey($plan->parent_id)->lockForUpdate()->firstOrFail();
            }
            $plan = CommitmentPlan::whereKey($plan->id)->lockForUpdate()->firstOrFail();
            if (! $plan->cycle->isOpen() || ! $plan->cycle->planning_enabled || ! in_array($plan->state, ['submitted', 'endorsed'], true)) {
                $this->fail('Only submitted commitments in an open planning period can be approved.');
            }
            if ($plan->level === 'ipcr' && ! $plan->agreed_at) {
                $this->fail('The employee must record agreement before commitment approval.');
            }
            if ($plan->level === 'opcr' && (empty($external['pmt_reviewer']) || empty($external['university_approver']) || empty($external['evidence']) || empty($external['approved_on']))) {
                $this->fail('Record the named PMT review and university approval evidence first.');
            }
            $awaitingParent = $plan->parent_id && $plan->parent->state !== 'approved';
            if ($plan->targets->groupBy('root_target_id')->contains(fn ($lines) => $lines->count() > 1)) {
                $this->fail('Keep one target line per OPCR source lineage in each plan.');
            }
            foreach ($plan->targets as $target) {
                if ($target->needs_reconciliation) {
                    $this->fail('Reconcile changed parent targets before approval.');
                }
                if (! $awaitingParent && $target->measurement === 'count' && $this->reviewedShares($target) > $target->quantity) {
                    $this->fail('Existing confirmed child shares exceed this revised quantity. Reconcile allocations before approval.');
                }
                if ($plan->parent_id) {
                    $parent = $target->parentTarget;
                    if (! $parent || $parent->plan_revision !== $plan->parent->revision || $target->source_revision !== $plan->parent->revision || ! $this->eligible($parent, $plan)) {
                        $this->fail('The source revision or eligibility changed. Refresh and reconcile this target.');
                    }
                    if ($parent->measurement === 'count') {
                        $this->allocation->assertAvailable((float) $parent->quantity, $awaitingParent ? 0 : $this->reviewedShares($parent, $plan->id), (float) $target->quantity);
                    }
                    $driver = DB::table('planning_drivers')->where('performance_target_id', $parent->id)->where('office_id', $plan->office_id)->where('employee_id', $plan->employee_id)->whereDate('valid_until', '>=', today())->first();
                    if ($parent->measurement === 'count' && $driver?->capacity !== null && $target->quantity > (float) $driver->capacity) {
                        $this->fail('The share exceeds recorded capacity. Record and explain a revised capacity before approval.');
                    }
                    if (! $awaitingParent && $parent->measurement === 'shared' && ! $target->contributes_only && $this->reviewedShares($parent, $plan->id) > 0) {
                        $this->fail('A shared output already has an accountable owner at this level. Record other involvement as a contribution.');
                    }
                }
            }
            if ($awaitingParent) {
                $plan->update(['state' => 'endorsed']);
                $this->event($plan, $actor, 'Reviewed; awaiting parent commitment approval');
                User::find($plan->prepared_by)?->notify(new PlanningNotification($plan, 'Your reviewer endorsed this draft. It is not confirmed until its parent commitments are approved.'));

                return;
            }
            $plan->update(['state' => 'approved', 'approved_at' => now(), 'approved_revision' => $plan->revision]);
            $this->event($plan, $actor, 'Commitments approved', $external);
            $this->project($plan);
            User::find($plan->prepared_by)?->notify(new PlanningNotification($plan, 'Commitments approved. Final performance review remains separate.'));
            foreach (CommitmentPlan::where('parent_id', $plan->id)->where('state', 'endorsed')->get() as $reviewedChild) {
                $this->approve($reviewedChild, $reviewedChild->reviewer, ['published_after_parent_approval' => true]);
            }
        });
    }

    public function amend(CommitmentPlan $plan, User $actor, string $reason): void
    {
        abort_unless($this->organization->canPrepare($actor, $plan), 403);
        DB::transaction(function () use ($plan, $actor, $reason) {
            $this->lockRoot($plan);
            $plan = CommitmentPlan::whereKey($plan->id)->lockForUpdate()->firstOrFail();
            if (! $plan->cycle->isOpen() || $plan->state !== 'approved') {
                $this->fail('Only an approved plan in an open period can be amended.');
            }
            if ($plan->report->status === 'approved') {
                $this->fail('The final performance report is archived. Use a later period rather than changing its commitments.');
            }
            $old = $plan->targets()->get();
            $plan->update(['state' => 'draft', 'revision' => $plan->revision + 1, 'approved_at' => null, 'agreed_at' => null]);
            foreach ($old as $target) {
                $copy = $target->replicate();
                $copy->plan_revision = $plan->revision;
                $copy->supersedes_id = $target->id;
                $copy->save();
                if (! $target->parent_target_id) {
                    $copy->update(['root_target_id' => $target->root_target_id ?: $target->id]);
                }
                foreach (['target_eligibilities', 'planning_drivers'] as $table) {
                    foreach (DB::table($table)->where('performance_target_id', $target->id)->get() as $row) {
                        $attributes = (array) $row;
                        unset($attributes['id']);
                        DB::table($table)->insert([...$attributes, 'performance_target_id' => $copy->id]);
                    }
                }
            }
            $frontier = [$plan->id];
            while ($frontier) {
                $children = CommitmentPlan::whereIn('parent_id', $frontier)->get();
                foreach ($children as $child) {
                    $child->targets()->update(['needs_reconciliation' => true]);
                    if ($child->state === 'endorsed') {
                        $child->update(['state' => 'draft', 'agreed_at' => null]);
                    }
                    $this->event($child, $actor, 'Parent revision needs reconciliation', ['parent_plan_id' => $plan->id, 'reason' => $reason]);
                }
                $frontier = $children->modelKeys();
            }
            $this->event($plan, $actor, 'Amendment opened', ['reason' => $reason, 'revision' => $plan->revision]);
        });
    }

    public function summary(PerformanceTarget $target): array
    {
        $ids = [$target->id];
        $frontier = $ids;
        // Include previous revisions of this same commitment without losing accepted evidence.
        $previous = $target;
        while ($previous->supersedes_id) {
            $previous = PerformanceTarget::findOrFail($previous->supersedes_id);
            $ids[] = $previous->id;
            $frontier[] = $previous->id;
        }
        while ($frontier) {
            $frontier = PerformanceTarget::whereIn('parent_target_id', $frontier)->pluck('id')->all();
            $ids = array_merge($ids, $frontier);
        }
        $outputs = PlanningOutput::whereIn('performance_target_id', array_unique($ids))->get();
        if ($target->contributes_only) {
            $outputs = $target->plan->state === 'approved' ? PlanningOutput::where('root_target_id', $target->root_target_id)->get() : collect();
        }
        $value = function ($rows) use ($target) {
            $rows = $rows->groupBy(function ($out) {
                $line = $out->target;
                while ($line->supersedes_id) {
                    $line = PerformanceTarget::findOrFail($line->supersedes_id);
                }

                return $line->id;
            })->flatMap(fn ($group) => $group->first()->submission_mode === 'cumulative' ? [$group->sortByDesc('id')->first()] : $group->all());
            if ($target->measurement === 'rate') {
                $denominator = (float) $rows->sum('denominator');

                return $denominator > 0 ? round(100 * $rows->sum('numerator') / $denominator, 2) : null;
            }
            if ($target->measurement === 'manual') {
                return null;
            }

            return (float) $rows->sum('quantity');
        };
        $reported = $target->contributes_only ? null : $value($outputs);
        $validated = $target->contributes_only ? null : $value($outputs->whereNotNull('validated_at'));

        return ['required' => $target->quantity, 'reported' => $reported, 'validated' => $validated,
            'percent' => $reported === null || ! $target->quantity ? null : round(100 * $reported / $target->quantity, 2),
            'unallocated' => $target->measurement === 'count' && $target->plan->level !== 'ipcr' ? max(0, $target->quantity - $this->used($target)) : null,
            'extra' => $reported === null || ! in_array($target->measurement, ['count', 'shared']) ? null : max(0, $reported - $target->quantity), 'outputs' => $outputs];
    }

    public function project(CommitmentPlan $plan): void
    {
        foreach ($plan->targets as $line) {
            if ($line->contributes_only) {
                continue;
            }
            $root = $line;
            while ($root->parent_target_id) {
                $root = $root->parentTarget;
            }
            if ($root->measurement === 'manual') {
                continue;
            }
            $identity = $root->root_target_id ?: $root->id;
            $projection = OfficeTarget::where('performance_target_id', $identity)->first() ?? new OfficeTarget;
            $projection->forceFill(['performance_target_id' => $identity, 'title' => $root->target, 'description' => 'Confirmed commitments. Manage quantities and evidence in Commitment Planning.', 'evaluation_cycle_id' => $root->plan->evaluation_cycle_id, 'success_indicator' => $root->success_indicator, 'target_value' => $root->quantity, 'unit_of_measure' => $root->unit, 'weight' => $root->weight, 'deadline' => $root->deadline, 'created_by' => $root->created_by])->save();
            if ($plan->level === 'dpcr') {
                $projection->offices()->syncWithoutDetaching([$plan->office_id => ['allocated_target_value' => $line->quantity]]);
            }
            if ($plan->level === 'ipcr') {
                $summary = $this->summary($line);
                if (! $projection->offices()->whereKey($plan->office_id)->exists()) {
                    $projection->offices()->syncWithoutDetaching([$plan->office_id => ['allocated_target_value' => $line->parentTarget?->quantity ?? $line->quantity]]);
                }
                $projection->assignedEmployees()->syncWithoutDetaching([$plan->employee_id => ['assigned_by' => $plan->prepared_by, 'accepted_at' => $plan->agreed_at, 'allocated_target_value' => $line->quantity, 'completed_value' => $summary['reported'] ?? 0]]);
            }
        }
    }
}
