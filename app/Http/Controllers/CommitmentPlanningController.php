<?php

namespace App\Http\Controllers;

use App\Models\CommitmentPlan;
use App\Models\Evaluation;
use App\Models\EvaluationCycle;
use App\Models\Office;
use App\Models\OfficeTarget;
use App\Models\PerformanceTarget;
use App\Models\PlanningEvent;
use App\Models\PlanningOutput;
use App\Models\TargetDocumentDraft;
use App\Models\TargetProposal;
use App\Models\User;
use App\Notifications\PlanningNotification;
use App\Notifications\TargetProposalNotification;
use App\Services\AiAssistant;
use App\Services\CommitmentPlanning;
use Barryvdh\DomPDF\Facade\Pdf;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;

class CommitmentPlanningController extends Controller
{
    public function __construct(private CommitmentPlanning $planning) {}

    private function viewPlan(Request $request, CommitmentPlan $plan): void
    {
        abort_unless($this->planning->organization->canView($request->user(), $plan), 403);
    }

    private function manage(Request $request, CommitmentPlan $plan): void
    {
        abort_unless($this->planning->organization->canPrepare($request->user(), $plan), 403);
    }

    private function notifyProposal(TargetProposal $proposal): void
    {
        $office = Office::findOrFail($proposal->review_office_id);
        $recipient = $office->head_id === $proposal->submitted_by ? User::role('Administrator')->whereKeyNot($proposal->submitted_by)->first() : $office->head;
        $recipient?->notify(new TargetProposalNotification($proposal->id, 'A target proposal is ready for supervisory review.'));
    }

    private function targetData(Request $r): array
    {
        return $r->validate(['target' => ['required', 'string', 'max:255'], 'success_indicator' => ['required', 'string', 'max:5000'], 'measurement' => ['required', Rule::in(['count', 'rate', 'shared', 'manual'])], 'quantity' => ['required', 'numeric', 'gt:0', 'max:9999999999.99', 'decimal:0,2'], 'unit' => ['required', 'string', 'max:100'], 'whole_numbers' => ['required', 'boolean'], 'standards' => ['required', 'string', 'max:5000'], 'deadline' => ['required', 'date'], 'source_evidence' => ['required', 'string', 'max:10000'], 'weight' => ['required', 'numeric', 'min:0', 'max:100']]);
    }

    public function index(Request $request)
    {
        $plans = CommitmentPlan::with(['office', 'cycle', 'employee'])->latest()->get()->filter(fn ($p) => $this->planning->organization->canView($request->user(), $p));
        $offices = Office::with(['head', 'planningReviewer'])->orderBy('name')->get();
        $parents = CommitmentPlan::with('office')->get()->filter(fn ($p) => $this->planning->organization->canView($request->user(), $p) || ($request->user()->office && in_array($p->office_id, [$request->user()->office_id, $request->user()->office->parent_office_id], true)));
        $people = User::with('office')->orderBy('name')->get()->filter(fn ($u) => $request->user()->hasRole('Administrator') || $u->id === $request->user()->id || $u->office?->head_id === $request->user()->id);

        return view('planning.index', ['plans' => $plans, 'parentPlans' => $parents, 'cycles' => EvaluationCycle::orderByDesc('year')->get(), 'offices' => $offices, 'people' => $people, 'proposals' => TargetProposal::latest()->get()->filter(fn ($p) => $p->submitted_by === $request->user()->id || $this->planning->organization->isOwner($request->user(), Office::findOrFail($p->review_office_id)))]);
    }

    public function organization(Request $r, Office $office)
    {
        abort_unless($r->user()->hasRole('Administrator'), 403);
        $data = $r->validate(['parent_office_id' => ['nullable', 'integer', Rule::exists('offices', 'id')], 'planning_level' => ['required', Rule::in(['office', 'division', 'unit'])], 'mandate' => ['required', 'string', 'max:10000'], 'head_id' => ['required', Rule::exists('users', 'id')], 'planning_reviewer_id' => ['required', Rule::exists('users', 'id')], 'verified' => ['accepted']]);
        DB::transaction(function () use ($office, $data, $r) {
            // Serialize hierarchy changes so concurrent reparenting cannot create cycles.
            Office::orderBy('id')->lockForUpdate()->get();
            if (CommitmentPlan::where('office_id', $office->id)->exists()) {
                throw ValidationException::withMessages(['office_id' => 'This office has commitment plans. Keep the historical reporting structure; configure a new office for a changed structure.']);
            }
            unset($data['verified']);
            $office->update([...$data, 'hierarchy_verified_at' => now()]);
            $office->refresh();
            $this->planning->organization->verify($office);
            $this->planning->event(null, $r->user(), 'Organization verified', ['office_id' => $office->id, ...$data]);
        });

        return back()->with('status', 'Reporting structure verified.');
    }

    public function duties(Request $r, User $person)
    {
        abort_unless($r->user()->hasRole('Administrator') || ($person->office && $this->planning->organization->isOwner($r->user(), $person->office)), 403);
        $person->update($r->validate(['planning_duties' => ['required', 'string', 'max:10000']]));
        $this->planning->event(null, $r->user(), 'Duties recorded', ['employee_id' => $person->id]);

        return back()->with('status', 'Personnel duties recorded.');
    }

    public function activate(Request $r, EvaluationCycle $cycle)
    {
        abort_unless($r->user()->hasRole('Administrator'), 403);
        $data = $r->validate(['planning_readiness' => ['required', 'string', 'min:20', 'max:5000'], 'confirmed' => ['accepted']]);
        DB::transaction(function () use ($cycle, $r, $data) {
            $cycle = EvaluationCycle::whereKey($cycle->id)->lockForUpdate()->firstOrFail();
            if ($cycle->planning_enabled) {
                return;
            }
            abort_unless($cycle->isOpen(), 422, 'Open this period first.');
            abort_if($cycle->opcrs()->exists() || OfficeTarget::where('evaluation_cycle_id', $cycle->id)->exists(), 422, 'Activate a new period before creating reports or assignments. Existing periods retain their workflow.');
            $roots = Office::where('planning_level', 'office')->whereNotNull('hierarchy_verified_at')->get();
            abort_if($roots->isEmpty(), 422, 'Verify an office reporting structure first.');
            foreach ($roots as $office) {
                $this->planning->organization->verify($office);
            }
            $cycle->update(['planning_enabled' => true, 'planning_readiness' => $data['planning_readiness']]);
            $this->planning->event(null, $r->user(), 'Planning activated', ['cycle_id' => $cycle->id, 'readiness' => $data['planning_readiness']]);
        });

        return back()->with('status', 'Commitment planning enabled for this period.');
    }

    public function store(Request $r)
    {
        $d = $r->validate(['evaluation_cycle_id' => ['required', Rule::exists('evaluation_cycles', 'id')], 'office_id' => ['required', Rule::exists('offices', 'id')], 'level' => ['required', Rule::in(array_keys(CommitmentPlanning::REPORTS))], 'parent_id' => ['nullable', Rule::exists('commitment_plans', 'id')], 'employee_id' => ['nullable', Rule::exists('users', 'id')], 'head_exception_reason' => ['nullable', 'string', 'max:5000']]);
        $p = $this->planning->createPlan($r->user(), EvaluationCycle::findOrFail($d['evaluation_cycle_id']), Office::findOrFail($d['office_id']), $d['level'], CommitmentPlan::find($d['parent_id'] ?? null), User::find($d['employee_id'] ?? null), $d['head_exception_reason'] ?? null);

        return redirect()->route('planning.show', $p);
    }

    public function show(Request $r, CommitmentPlan $plan)
    {
        $this->viewPlan($r, $plan);
        $sources = $plan->parent?->targets()->get() ?? collect();
        $sources = $sources->filter(fn ($t) => $this->planning->eligible($t, $plan))->map(fn ($t) => ['target' => $t, 'eligible' => true, 'summary' => $this->planning->summary($t), 'suggestion' => $this->planning->suggestion($t)]);

        return view('planning.show', ['plan' => $plan, 'lines' => $plan->targets()->get()->map(fn ($t) => ['target' => $t, 'summary' => $this->planning->summary($t)]), 'sources' => $sources, 'canManage' => $this->planning->organization->canPrepare($r->user(), $plan), 'offices' => Office::where('parent_office_id', $plan->office_id)->get(), 'people' => User::where('office_id', $plan->office_id)->get(), 'eligibilities' => DB::table('target_eligibilities')->whereIn('performance_target_id', $plan->targets()->pluck('id'))->get(), 'events' => $plan->events()->with('actor')->paginate(20)]);
    }

    public function target(Request $r, CommitmentPlan $plan)
    {
        $this->manage($r, $plan);
        if ($plan->level === 'opcr') {
            $data = $this->targetData($r);
        } else {
            $data = $r->validate(['parent_target_id' => ['required', Rule::exists('performance_targets', 'id')], 'quantity' => ['required', 'numeric', 'gt:0', 'max:9999999999.99', 'decimal:0,2'], 'weight' => ['required', 'numeric', 'min:0', 'max:100'], 'override_reason' => ['required', 'string', 'max:5000'], 'contributes_only' => ['sometimes', 'boolean']]);
            $source = PerformanceTarget::findOrFail($data['parent_target_id']);
            abort_unless($source->commitment_plan_id === $plan->parent_id, 403);
            $suggestion = $this->planning->suggestion($source);
            $local = $r->validate(['local_target' => ['nullable', 'string', 'max:255']]);
            $data['target'] = $local['local_target'] ?? mb_substr('Local commitment: '.$data['quantity'].' '.$source->unit.' — '.$source->target, 0, 255);
            $data['allocation_basis'] = ['suggestion' => $suggestion, 'confirmed_quantity' => $data['quantity'], 'override_reason' => $data['override_reason'], 'confirmed_by' => $r->user()->id, 'period_id' => $plan->evaluation_cycle_id];
            unset($data['parent_target_id'], $data['override_reason']);
        }
        $this->planning->addTarget($plan, $r->user(), $data, $source ?? null);

        return back()->with('status', 'Target added to the draft. Quantities are reserved only on approval.');
    }

    public function updateTarget(Request $r, PerformanceTarget $target)
    {
        $plan = $target->plan;
        abort_unless($plan, 404);
        $this->manage($r, $plan);
        DB::transaction(function () use ($r, $target, $plan) {
            $this->planning->lockRoot($plan);
            $locked = CommitmentPlan::whereKey($plan->id)->lockForUpdate()->firstOrFail();
            $this->planning->writable($locked);
            abort_unless($target->plan_revision === $locked->revision, 422);
            if ($plan->level === 'opcr') {
                $data = $this->targetData($r);
            } else {
                $data = $r->validate(['quantity' => ['required', 'numeric', 'gt:0', 'max:9999999999.99', 'decimal:0,2'], 'weight' => ['required', 'numeric', 'min:0', 'max:100'], 'source_target_id' => ['required', Rule::exists('performance_targets', 'id')], 'override_reason' => ['required', 'string', 'max:5000']]);
                $source = PerformanceTarget::findOrFail($data['source_target_id']);
                abort_unless($source->commitment_plan_id === $plan->parent_id && $source->plan_revision === $plan->parent->revision && $this->planning->eligible($source, $plan), 422, 'Select a current eligible parent target.');
                abort_unless($source->root_target_id === $target->root_target_id, 422, 'Reconcile with the same OPCR target lineage. Add a separate target for a different responsibility.');
                foreach (['measurement', 'unit', 'whole_numbers', 'standards', 'deadline', 'source_evidence', 'success_indicator'] as $key) {
                    $data[$key] = $source->$key;
                }
                if ($source->measurement !== 'count') {
                    $data['quantity'] = $source->quantity;
                }
                $data += ['parent_target_id' => $source->id, 'source_revision' => $plan->parent->revision, 'needs_reconciliation' => false, 'allocation_basis' => ['override_reason' => $data['override_reason'], 'confirmed_by' => $r->user()->id, 'suggestion' => $this->planning->suggestion($source)]];
                unset($data['source_target_id'], $data['override_reason']);
            }
            if (($data['whole_numbers'] ?? true) && $data['measurement'] === 'count' && floor((float) $data['quantity']) !== (float) $data['quantity']) {
                throw ValidationException::withMessages(['quantity' => 'Enter a whole number.']);
            }
            if ($data['measurement'] === 'rate') {
                abort_if($data['quantity'] > 100, 422, 'A percentage standard cannot exceed 100%.');
            }
            if ($plan->level === 'opcr' && $target->supersedes_id) {
                $previous = PerformanceTarget::findOrFail($target->supersedes_id);
                abort_if($data['measurement'] !== $previous->measurement || $data['unit'] !== $previous->unit, 422, 'Keep the approved measurement and unit. Add a separate target for a different measure.');
            }
            if ($data['measurement'] === 'shared') {
                $data['quantity'] = $target->contributes_only ? 0 : 1;
            }
            $target->update($data);
            $locked->update(['agreed_at' => null]);
            $this->planning->event($plan, $r->user(), 'Draft target changed', ['target_id' => $target->id]);
        });

        return back()->with('status', 'Draft target updated.');
    }

    public function eligibility(Request $r, PerformanceTarget $target)
    {
        abort_unless($target->plan, 404);
        $this->manage($r, $target->plan);
        $d = $r->validate(['office_id' => ['required', Rule::exists('offices', 'id')], 'employee_id' => ['nullable', Rule::exists('users', 'id')], 'reason' => ['required', 'string', 'max:5000']]);
        $office = Office::findOrFail($d['office_id']);
        $this->planning->organization->verify($office);
        $person = User::find($d['employee_id'] ?? null);
        if ($person) {
            abort_unless($person->office_id === $office->id && $office->id === $target->plan->office_id && trim($person->planning_duties ?? ''), 422, 'Confirm this employee’s office and duties first.');
        } else {
            abort_unless($office->parent_office_id === $target->plan->office_id, 422, 'Choose a direct subordinate office.');
        }
        DB::transaction(function () use ($target, $office, $person, $d, $r) {
            $this->planning->lockRoot($target->plan);
            DB::table('target_eligibilities')->updateOrInsert(['performance_target_id' => $target->id, 'office_id' => $office->id, 'employee_id' => $person?->id], ['reason' => $d['reason'], 'confirmed_by' => $r->user()->id, 'created_at' => now(), 'updated_at' => now()]);
            $this->planning->event($target->plan, $r->user(), 'Relevance confirmed', $d);
            ($person ?? $office->head)?->notify(new PlanningNotification($target->plan, 'A target has been confirmed as relevant to your duties. Open Commitment Planning to prepare or review your draft.', route('planning.index')));
        });

        return back()->with('status', 'Target relevance confirmed.');
    }

    public function driver(Request $r, PerformanceTarget $target)
    {
        abort_unless($target->plan, 404);
        $this->manage($r, $target->plan);
        $d = $r->validate(['office_id' => ['required', Rule::exists('offices', 'id')], 'employee_id' => ['nullable', Rule::exists('users', 'id')], 'name' => ['required', 'string', 'max:255'], 'source' => ['required', 'string', 'max:5000'], 'value' => ['required', 'numeric', 'gt:0', 'max:9999999999.99'], 'capacity' => ['nullable', 'numeric', 'min:0', 'max:9999999999.99'], 'valid_until' => ['required', 'date', 'after_or_equal:today']]);
        $office = Office::findOrFail($d['office_id']);
        $person = User::find($d['employee_id'] ?? null);
        abort_unless($person ? ($person->office_id === $office->id && $office->id === $target->plan->office_id) : $office->parent_office_id === $target->plan->office_id, 422);
        abort_unless(DB::table('target_eligibilities')->where('performance_target_id', $target->id)->where('office_id', $office->id)->where('employee_id', $person?->id)->exists(), 422, 'Confirm relevance before recording an allocation driver.');
        $keys = ['performance_target_id' => $target->id, 'office_id' => $office->id, 'employee_id' => $person?->id];
        $others = DB::table('planning_drivers')->where('performance_target_id', $target->id)->where('name', '!=', $d['name'])->exists();
        abort_if($others, 422, 'Use the same confirmed driver name and unit for every recipient of this target.');
        DB::transaction(function () use ($target, $keys, $d, $person, $r) {
            $this->planning->lockRoot($target->plan);
            DB::table('planning_drivers')->updateOrInsert($keys, [...$d, 'employee_id' => $person?->id, 'confirmed_by' => $r->user()->id, 'created_at' => now(), 'updated_at' => now()]);
            $this->planning->event($target->plan, $r->user(), 'Allocation driver recorded', $d);
        });

        return back()->with('status', 'Allocation driver recorded.');
    }

    public function suggest(Request $r, CommitmentPlan $plan, AiAssistant $ai)
    {
        $this->manage($r, $plan);
        $context = ['office_mandate' => $plan->office->mandate, 'employee_duties' => $plan->employee?->planning_duties, 'targets' => $plan->parent?->targets()->get(['id', 'target', 'success_indicator', 'standards'])->toArray() ?? []];
        try {
            $draft = $ai->draft('cascade', $context);
        } catch (\RuntimeException $e) {
            return back()->withErrors(['ai' => $e->getMessage()]);
        }
        $this->planning->event($plan, $r->user(), 'AI relevance draft', ['draft' => $draft]);

        return back()->with('ai_draft', $draft);
    }

    public function transition(Request $r, CommitmentPlan $plan, string $action)
    {
        $this->viewPlan($r, $plan);
        if ($action === 'submit') {
            $this->planning->submit($plan, $r->user());
        } elseif ($action === 'agree') {
            abort_unless($plan->employee_id === $r->user()->id && in_array($plan->state, ['draft', 'submitted']) && $plan->cycle->isOpen(), 403);
            $r->validate(['confirmed' => ['accepted']]);
            $plan->update(['agreed_at' => now()]);
            $this->planning->event($plan, $r->user(), 'Employee agreed');
        } elseif ($action === 'approve') {
            $d = $r->validate(['pmt_reviewer' => ['nullable', 'string', 'max:255'], 'university_approver' => ['nullable', 'string', 'max:255'], 'approved_on' => ['nullable', 'date', 'before_or_equal:today'], 'evidence' => ['nullable', 'file', 'mimes:pdf,jpg,jpeg,png', 'max:10240']]);
            $path = $r->file('evidence')?->store('planning-approvals', 'local');
            try {
                $this->planning->approve($plan, $r->user(), [...$d, 'evidence' => $path]);
            } catch (\Throwable $e) {
                if ($path) {
                    Storage::disk('local')->delete($path);
                } throw $e;
            }
        } elseif ($action === 'amend') {
            $d = $r->validate(['reason' => ['required', 'string', 'max:5000']]);
            $this->planning->amend($plan, $r->user(), $d['reason']);
        } elseif ($action === 'return') {
            abort_unless($r->user()->id === $plan->reviewer_id && in_array($plan->state, ['submitted', 'endorsed'], true), 403);
            $d = $r->validate(['reason' => ['required', 'string', 'max:5000']]);
            $plan->update(['state' => 'draft', 'agreed_at' => null]);
            $this->planning->event($plan, $r->user(), 'Returned for correction', $d);
        } else {
            abort(404);
        }

        return back()->with('status', 'Commitment planning updated.');
    }

    public function output(Request $r, PerformanceTarget $target)
    {
        abort_unless($target->plan, 404);
        $p = $target->plan;
        abort_unless(($p->employee_id ? $p->employee_id === $r->user()->id : $p->office->head_id === $r->user()->id), 403);
        abort_unless($p->state === 'approved' && $p->cycle->isOpen() && $target->plan_revision === $p->revision && ! $target->needs_reconciliation, 422, 'Approve and reconcile commitments before reporting output.');
        abort_if($target->contributes_only, 422, 'Record a contribution against the accountable owner’s output; do not report another copy.');
        abort_if(PerformanceTarget::where('parent_target_id', $target->id)->whereHas('plan', fn ($q) => $q->where('state', 'approved'))->exists(), 422, 'Record work against its responsible lower-level target, not its parent summary.');
        $d = $r->validate(['reference' => ['required', 'string', 'max:255'], 'details' => ['required', 'string', 'max:5000'], 'quantity' => ['required', 'numeric', 'gt:0', 'max:9999999999.99', 'decimal:0,2'], 'numerator' => ['nullable', 'numeric', 'min:0', 'max:9999999999.99'], 'denominator' => ['nullable', 'numeric', 'gt:0', 'max:9999999999.99'], 'evidence' => ['required', 'file', 'mimes:pdf,jpg,jpeg,png', 'max:10240']]);
        if ($target->measurement === 'rate') {
            $r->validate(['numerator' => ['required', 'numeric', 'min:0', 'lte:denominator'], 'denominator' => ['required', 'numeric', 'gt:0']]);
        }
        if ($target->measurement === 'shared') {
            $d['quantity'] = 1;
        }
        if ($target->measurement === 'count' && $target->whole_numbers) {
            abort_if(floor((float) $d['quantity']) !== (float) $d['quantity'], 422, 'Record a whole output quantity.');
        }
        $mode = $r->validate(['submission_mode' => ['sometimes', Rule::in(['incremental', 'cumulative'])]]);
        $d['submission_mode'] = $mode['submission_mode'] ?? 'incremental';
        $d['reference'] = mb_strtoupper(trim($d['reference']));
        $path = $r->file('evidence')->store('planning-outputs', 'local');
        try {
            DB::transaction(function () use ($d, $r, $path, $target, $p) {
                $this->planning->lockRoot($p);
                $p->refresh();
                $target->refresh();
                abort_unless($p->state === 'approved' && $p->cycle->isOpen() && ! $target->needs_reconciliation && $p->report->status !== 'approved', 422, 'Commitments changed or the final report is archived. Refresh before reporting.');
                PerformanceTarget::whereKey($target->root_target_id)->lockForUpdate()->firstOrFail();
                $identityIds = [$target->id];
                $previous = $target;
                while ($previous->supersedes_id) {
                    $previous = PerformanceTarget::findOrFail($previous->supersedes_id);
                    $identityIds[] = $previous->id;
                }
                $last = PlanningOutput::whereIn('performance_target_id', $identityIds)->latest('id')->first();
                abort_if($last && $last->submission_mode !== $d['submission_mode'], 422, 'Keep the original incremental or cumulative reporting method for this assignment.');
                if ($last && $d['submission_mode'] === 'cumulative') {
                    abort_if($d['quantity'] < $last->quantity, 422, 'A cumulative quantity cannot decrease. Submit a correction for reviewer reconciliation instead.');
                    if ($target->measurement === 'rate') {
                        abort_if($d['numerator'] < $last->numerator || $d['denominator'] < $last->denominator, 422, 'Cumulative case counts cannot decrease.');
                    }
                }
                abort_if(PlanningOutput::where('root_target_id', $target->root_target_id)->where('reference', $d['reference'])->exists(), 422, 'This output reference is already recorded. Attach a contribution instead of counting it again.');
                unset($d['evidence']);
                $out = PlanningOutput::create([...$d, 'performance_target_id' => $target->id, 'root_target_id' => $target->root_target_id, 'evidence' => ['path' => $path, 'name' => $r->file('evidence')->getClientOriginalName()], 'submitted_by' => $r->user()->id]);
                $this->planning->event($p, $r->user(), 'Output reported', ['output_id' => $out->id, 'reference' => $d['reference']]);
                $p->reviewer->notify(new PlanningNotification($p, 'Supporting output is ready for validation.'));
                $this->planning->project($p);
            });
        } catch (\Throwable $e) {
            Storage::disk('local')->delete($path);
            throw $e;
        }

        return back()->with('status', 'Output recorded as reported work, pending validation.');
    }

    public function validateOutput(Request $r, PlanningOutput $output)
    {
        $p = $output->target->plan;
        abort_unless($p->reviewer_id === $r->user()->id && $output->submitted_by !== $r->user()->id && $p->cycle->isOpen(), 403);
        DB::transaction(function () use ($output, $r, $p) {
            $this->planning->lockRoot($p);
            abort_if($p->report->status === 'approved', 422, 'The final performance report is archived.');
            $output = PlanningOutput::whereKey($output->id)->lockForUpdate()->firstOrFail();
            if ($output->validated_at) {
                return;
            }
            $output->update(['validated_at' => now(), 'validated_by' => $r->user()->id]);
            $this->planning->event($p, $r->user(), 'Output validated', ['output_id' => $output->id]);
            User::find($p->prepared_by)?->notify(new PlanningNotification($p, 'Supporting output was validated. Performance ratings remain separate.'));
        });

        return back()->with('status', 'Output validated. Ratings remain a separate decision.');
    }

    public function download(Request $r, PlanningOutput $output)
    {
        $canContribute = PerformanceTarget::where('root_target_id', $output->root_target_id)->where('contributes_only', true)->get()->contains(fn ($t) => $this->planning->organization->canView($r->user(), $t->plan));
        abort_unless($canContribute || $this->planning->organization->canView($r->user(), $output->target->plan), 403);

        $file = $output->evidence;
        if ($r->filled('attachment')) {
            $d = $r->validate(['attachment' => ['required', 'integer', 'min:0', 'max:9']]);
            $file = $output->evidence['attachments'][$d['attachment']] ?? abort(404);
        }

        return Storage::disk('local')->download($file['path'], $file['name']);
    }

    public function supplement(Request $r, PlanningOutput $output)
    {
        $plan = $output->target->plan;
        abort_unless($output->submitted_by === $r->user()->id && $plan->cycle->isOpen() && $plan->report->status !== 'approved', 403);
        $r->validate(['evidence' => ['required', 'file', 'mimes:pdf,jpg,jpeg,png', 'max:10240']]);
        $path = $r->file('evidence')->store('planning-outputs', 'local');
        try {
            DB::transaction(function () use ($output, $plan, $path, $r) {
                $this->planning->lockRoot($plan);
                $output = PlanningOutput::whereKey($output->id)->lockForUpdate()->firstOrFail();
                abort_if($plan->report->fresh()->status === 'approved', 422, 'The final performance report is archived.');
                $evidence = $output->evidence;
                $files = $evidence['attachments'] ?? [];
                abort_if(count($files) >= 10, 422, 'This output already has ten additional files.');
                $files[] = ['path' => $path, 'name' => $r->file('evidence')->getClientOriginalName()];
                $output->update(['evidence' => [...$evidence, 'attachments' => $files]]);
                $this->planning->event($plan, $r->user(), 'Supporting file added without changing output quantity', ['output_id' => $output->id]);
                $plan->reviewer->notify(new PlanningNotification($plan, 'Additional supporting evidence is available. The reported quantity is unchanged.'));
            });
        } catch (\Throwable $e) {
            Storage::disk('local')->delete($path);
            throw $e;
        }

        return back()->with('status', 'Supporting file added. The output was not counted again.');
    }

    public function contribution(Request $r, PlanningOutput $output)
    {
        $d = $r->validate(['performance_target_id' => ['required', Rule::exists('performance_targets', 'id')], 'details' => ['required', 'string', 'max:5000']]);
        $target = PerformanceTarget::findOrFail($d['performance_target_id']);
        abort_unless($target->plan && $target->root_target_id === $output->root_target_id, 422);
        $this->manage($r, $target->plan);
        DB::table('planning_contributions')->updateOrInsert(['planning_output_id' => $output->id, 'performance_target_id' => $target->id], ['details' => $d['details'], 'created_at' => now(), 'updated_at' => now()]);
        $this->planning->event($target->plan, $r->user(), 'Contribution recorded without additional quantity', ['output_id' => $output->id]);

        return back()->with('status', 'Contribution recorded; the output is still counted once.');
    }

    public function rate(Request $r, PerformanceTarget $target)
    {
        $plan = $target->plan;
        abort_unless($plan, 404);
        $employeeId = $plan->employee_id ?: $plan->office->head_id;
        abort_unless($plan->reviewer_id === $r->user()->id && $employeeId !== $r->user()->id, 403);
        abort_unless($plan->state === 'approved' && $plan->cycle->isOpen() && $target->plan_revision === $plan->revision && ! $target->needs_reconciliation, 422);
        $summary = $this->planning->summary($target);
        abort_if($summary['outputs']->isEmpty() || $summary['outputs']->whereNull('validated_at')->isNotEmpty(), 422, 'Validate supporting output before recording a rating.');
        $d = $r->validate(['quality_rating' => ['required', 'numeric', 'min:1', 'max:5'], 'efficiency_rating' => ['required', 'numeric', 'min:1', 'max:5'], 'timeliness_rating' => ['required', 'numeric', 'min:1', 'max:5'], 'remarks' => ['nullable', 'string', 'max:5000']]);
        $average = Evaluation::computeAverage($d['quality_rating'], $d['efficiency_rating'], $d['timeliness_rating']);
        DB::transaction(function () use ($target, $plan, $r, $d, $average, $employeeId) {
            CommitmentPlan::whereKey($plan->id)->lockForUpdate()->firstOrFail();
            abort_if($plan->report->status === 'approved', 422, 'The final performance report is already approved.');
            Evaluation::updateOrCreate(['performance_target_id' => $target->id, 'employee_id' => $employeeId], [...$d, 'rated_by' => $r->user()->id, 'rating' => $average, 'computed_rating' => Evaluation::computeRating($average, $target->weight), 'adjectival_rating' => Evaluation::adjectivalRating($average)]);
            $this->planning->event($plan, $r->user(), 'Performance rating recorded', ['target_id' => $target->id, 'ratings' => $d]);
        });

        return back()->with('status', 'Reviewer rating recorded separately from commitment approval.');
    }

    public function approvalEvidence(Request $r, CommitmentPlan $plan, PlanningEvent $event)
    {
        $this->viewPlan($r, $plan);
        abort_unless($event->commitment_plan_id === $plan->id && ! empty($event->data['evidence']), 404);

        return Storage::disk('local')->download($event->data['evidence']);
    }

    public function resubmitProposal(Request $r, TargetProposal $proposal)
    {
        abort_unless($proposal->submitted_by === $r->user()->id && $proposal->state === 'returned', 403);
        abort_unless(EvaluationCycle::findOrFail($proposal->evaluation_cycle_id)->isOpen(), 422);
        $data = $this->targetData($r);
        $origin = Office::findOrFail($proposal->office_id);
        $reviewOffice = $origin->head_id === $r->user()->id ? ($origin->parent_office_id ?: $origin->id) : $origin->id;
        $proposal->update(['proposal' => $data, 'state' => 'pending', 'review_office_id' => $reviewOffice]);
        $this->planning->event(null, $r->user(), 'Proposal revised and resubmitted', ['proposal_id' => $proposal->id]);
        $this->notifyProposal($proposal);

        return back()->with('status', 'Revised proposal resubmitted.');
    }

    public function import(Request $r, CommitmentPlan $plan, TargetDocumentDraft $draft)
    {
        $this->manage($r, $plan);
        abort_unless($plan->level === 'opcr' && $draft->document->created_by === $r->user()->id && $draft->document->evaluation_cycle_id === $plan->evaluation_cycle_id, 403);
        $data = $this->targetData($r);
        $data['source_evidence'] = 'Document #'.$draft->document->id.' / draft #'.$draft->id.': '.$draft->proposal['source_excerpt'];
        DB::transaction(function () use ($r, $plan, $draft, $data) {
            $draft = TargetDocumentDraft::whereKey($draft->id)->lockForUpdate()->firstOrFail();
            abort_if($draft->performance_target_id, 422, 'This extracted target has already been imported.');
            $t = $this->planning->addTarget($plan, $r->user(), $data);
            $draft->forceFill(['performance_target_id' => $t->id])->save();
        });

        return redirect()->route('planning.show', $plan)->with('status', 'Extracted target saved in the OPCR draft.');
    }

    public function proposal(Request $r)
    {
        $data = $this->targetData($r);
        $d = $r->validate(['evaluation_cycle_id' => ['required', Rule::exists('evaluation_cycles', 'id')], 'amends_target_id' => ['nullable', Rule::exists('performance_targets', 'id')]]);
        $office = $r->user()->office;
        abort_unless($office, 403);
        $this->planning->organization->verify($office);
        $cycle = EvaluationCycle::findOrFail($d['evaluation_cycle_id']);
        abort_unless($cycle->planning_enabled && $cycle->isOpen(), 422);
        $amends = PerformanceTarget::find($d['amends_target_id'] ?? null);
        if ($amends) {
            $applicable = PerformanceTarget::where('root_target_id', $amends->root_target_id)->whereIn('commitment_plan_id', $this->planning->organization->visiblePlanIds($r->user()))->exists();
            $eligible = DB::table('target_eligibilities')->where('performance_target_id', $amends->id)->where('office_id', $office->id)->where(fn ($q) => $q->where('employee_id', $r->user()->id)->when($office->head_id === $r->user()->id, fn ($q) => $q->orWhereNull('employee_id')))->exists();
            abort_unless($amends->plan && ($applicable || $eligible || $this->planning->organization->canView($r->user(), $amends->plan)) && $amends->plan->level === 'opcr' && $amends->plan->evaluation_cycle_id === $cycle->id, 403);
        }
        $reviewOffice = $office->head_id === $r->user()->id ? ($office->parent_office_id ?: $office->id) : $office->id;
        $proposal = TargetProposal::create([...$d, 'office_id' => $office->id, 'submitted_by' => $r->user()->id, 'review_office_id' => $reviewOffice, 'proposal' => $data]);
        $this->planning->event(null, $r->user(), 'Upward proposal submitted', ['proposal_id' => $proposal->id]);
        $this->notifyProposal($proposal);

        return back()->with('status', 'Proposal submitted for supervisory review.');
    }

    public function reviewProposal(Request $r, TargetProposal $proposal)
    {
        $d = $r->validate(['decision' => ['required', Rule::in(['forward', 'reject', 'return', 'materialize'])], 'review_notes' => ['required', 'string', 'max:5000'], 'root_plan_id' => ['nullable', Rule::exists('commitment_plans', 'id')]]);
        DB::transaction(function () use ($proposal, $r, $d) {
            $proposal = TargetProposal::whereKey($proposal->id)->lockForUpdate()->firstOrFail();
            $office = Office::findOrFail($proposal->review_office_id);
            abort_unless($this->planning->organization->isOwner($r->user(), $office) && $r->user()->id !== $proposal->submitted_by, 403);
            abort_unless($proposal->state === 'pending' && EvaluationCycle::findOrFail($proposal->evaluation_cycle_id)->isOpen(), 422);
            if ($d['decision'] === 'forward') {
                abort_unless($office->parent_office_id, 422, 'At the top office, add this proposal to an OPCR draft.');
                $proposal->update(['review_office_id' => $office->parent_office_id, 'review_notes' => $d['review_notes']]);
            } elseif ($d['decision'] === 'materialize') {
                $root = CommitmentPlan::findOrFail($d['root_plan_id'] ?? null);
                abort_unless($root->level === 'opcr' && $root->office_id === $office->id && $root->evaluation_cycle_id === $proposal->evaluation_cycle_id, 422);
                $this->manage($r, $root);
                if ($root->state === 'approved') {
                    $this->planning->amend($root, $r->user(), $d['review_notes']);
                }
                $root->refresh();
                if ($proposal->amends_target_id) {
                    $old = PerformanceTarget::findOrFail($proposal->amends_target_id);
                    abort_unless($old->commitment_plan_id === $root->id, 422);
                    $this->planning->writable($root);
                    abort_if($proposal->proposal['measurement'] !== $old->measurement || $proposal->proposal['unit'] !== $old->unit, 422, 'A different measure needs a separate target proposal.');
                    $target = $root->targets()->where('root_target_id', $old->root_target_id)->firstOrFail();
                    $target->update($proposal->proposal);
                    $this->planning->event($root, $r->user(), 'Reviewed proposal applied to target revision', ['proposal_id' => $proposal->id, 'target_id' => $target->id]);
                } else {
                    $target = $this->planning->addTarget($root, $r->user(), $proposal->proposal);
                }
                $proposal->update(['state' => 'accepted', 'root_plan_id' => $root->id, 'result_target_id' => $target->id, 'review_notes' => $d['review_notes']]);
            } else {
                $proposal->update(['state' => $d['decision'] === 'return' ? 'returned' : 'rejected', 'review_notes' => $d['review_notes']]);
            }
            $this->planning->event(null, $r->user(), 'Proposal reviewed', ['proposal_id' => $proposal->id, ...$d]);
            if ($proposal->state === 'pending') {
                $this->notifyProposal($proposal);
            } else {
                User::find($proposal->submitted_by)?->notify(new TargetProposalNotification($proposal->id, 'Your target proposal was '.$proposal->state.'. '.$d['review_notes']));
            }
        });

        return back()->with('status', 'Proposal review recorded.');
    }

    public function export(Request $r, CommitmentPlan $plan, string $format)
    {
        $this->viewPlan($r, $plan);
        $data = ['plan' => $plan, 'lines' => $plan->targets()->get()->map(fn ($t) => ['target' => $t, 'summary' => $this->planning->summary($t)])];
        if ($format === 'pdf') {
            return Pdf::loadView('planning.export', $data)->setPaper('a4', 'landscape')->download('commitments-'.$plan->id.'.pdf');
        }
        abort_unless($format === 'excel', 404);

        return response()->streamDownload(fn () => print (view('planning.export', $data)->render()), 'commitments-'.$plan->id.'.xls', ['Content-Type' => 'application/vnd.ms-excel; charset=UTF-8']);
    }
}
