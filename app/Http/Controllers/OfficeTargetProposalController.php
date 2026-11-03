<?php

namespace App\Http\Controllers;

use App\Models\{EvaluationCycle, Office, OfficeTarget, TargetProposal, User, WorkflowAudit};
use App\Notifications\TargetProposalNotification;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;

class OfficeTargetProposalController extends Controller
{
    public function index(Request $request)
    {
        return view('office-target-proposals.index', ['proposals' => TargetProposal::with('office')->where('proposal->kind', 'office_target')
            ->when(! $request->user()->hasAnyRole(['Planning Officer', 'Administrator']), fn ($q) => $q->where('submitted_by', $request->user()->id))->latest()->paginate(15)]);
    }

    public function create(Request $request)
    {
        abort_unless($request->user()->canProposeOfficeTarget(), 403);
        $proposal = $request->filled('proposal_id') ? TargetProposal::findOrFail($request->integer('proposal_id')) : null;
        if ($proposal) abort_unless($proposal->submitted_by === $request->user()->id && $proposal->state === 'returned' && ($proposal->proposal['kind'] ?? '') === 'office_target', 403);
        return view('office-target-proposals.create', ['proposal' => $proposal, 'cycles' => EvaluationCycle::where('status', 'open')->where('planning_enabled', false)->orderByDesc('year')->get()]);
    }

    public function store(Request $request)
    {
        abort_unless($request->user()->canProposeOfficeTarget(), 403);
        $data = $request->validate([
            'evaluation_cycle_id' => ['required', 'integer', 'exists:evaluation_cycles,id'],
            'proposal_id' => ['nullable', 'integer', 'exists:target_proposals,id'],
            'program' => ['required', 'string', 'max:5000'],
            'program_outcome' => ['required', 'string', 'max:5000'],
            'title' => ['required', 'string', 'max:255'],
            'success_indicator' => ['required', 'string', 'max:5000'],
            'target_value' => ['required', 'numeric', 'min:0.01', 'max:9999999999.99', 'decimal:0,2'],
            'unit_of_measure' => ['required', 'string', 'max:100'],
            'weight' => ['required', 'numeric', 'min:0.01', 'max:100', 'decimal:0,2'],
            'priority' => ['required', Rule::in(OfficeTarget::PRIORITIES)],
            'justification' => ['required', 'string', 'max:5000'],
        ]);
        $proposal = DB::transaction(function () use ($request, $data) {
            $cycle = EvaluationCycle::whereKey($data['evaluation_cycle_id'])->lockForUpdate()->firstOrFail();
            abort_unless($cycle->isOpen() && ! $cycle->planning_enabled, 422, 'For commitment-planning periods, use Commitment Planning → Propose a target upward.');
            $office = Office::whereKey($request->user()->office_id)->lockForUpdate()->firstOrFail();
            $request->user()->setRelation('office', $office);
            abort_unless($request->user()->canProposeOfficeTarget(), 403);
            $payload = [...collect($data)->except(['proposal_id', 'evaluation_cycle_id'])->all(), 'kind' => 'office_target'];
            if (! empty($data['proposal_id'])) {
                $proposal = TargetProposal::whereKey($data['proposal_id'])->lockForUpdate()->firstOrFail();
                abort_unless($proposal->submitted_by === $request->user()->id && $proposal->office_id === $office->id && $proposal->state === 'returned' && ($proposal->proposal['kind'] ?? '') === 'office_target', 403);
                $proposal->update(['evaluation_cycle_id' => $cycle->id, 'proposal' => $payload, 'state' => 'pending', 'review_notes' => null]);
            } else {
                $proposal = TargetProposal::create(['evaluation_cycle_id' => $cycle->id, 'office_id' => $office->id, 'review_office_id' => $office->parent_office_id ?: $office->id, 'submitted_by' => $request->user()->id, 'proposal' => $payload]);
            }
            WorkflowAudit::record($proposal, 'Office head submitted target for Planning Officer validation', null, $proposal->toArray());
            return $proposal;
        });
        User::role(['Planning Officer', 'Administrator'])->whereKeyNot($request->user()->id)->each(fn ($person) => $person->notify(new TargetProposalNotification($proposal->id, 'An office head added a target for validation.', route('office-target-proposals.index', [], false))));
        return redirect()->route('office-target-proposals.index')->with('status', 'Target submitted for Planning Officer validation. It is not assigned or active yet.');
    }

    public function review(Request $request, TargetProposal $proposal)
    {
        abort_unless($request->user()->hasAnyRole(['Planning Officer', 'Administrator']), 403);
        $data = $request->validate(['decision' => ['required', Rule::in(['approve', 'return', 'reject'])], 'review_notes' => ['required', 'string', 'max:5000']]);
        DB::transaction(function () use ($proposal, $request, $data) {
            $proposal = TargetProposal::whereKey($proposal->id)->lockForUpdate()->firstOrFail();
            abort_unless(($proposal->proposal['kind'] ?? '') === 'office_target' && $proposal->submitted_by !== $request->user()->id, 403);
            if ($proposal->state === 'accepted' && $data['decision'] === 'approve') return;
            abort_unless($proposal->state === 'pending', 422, 'This proposal has already been reviewed.');
            $cycle = EvaluationCycle::whereKey($proposal->evaluation_cycle_id)->lockForUpdate()->firstOrFail();
            abort_unless($cycle->isOpen() && ! $cycle->planning_enabled, 422, 'The evaluation period is not open for this target.');
            $values = $proposal->proposal;
            if ($data['decision'] === 'approve') {
                $office = Office::whereKey($proposal->office_id)->lockForUpdate()->firstOrFail();
                abort_unless($office->is_active && (int) $office->campus === (int) config('hinunangan.campus_id'), 422, 'The proposing office is no longer active in this campus.');
                $target = OfficeTarget::create([...collect($values)->only(['title', 'success_indicator', 'target_value', 'unit_of_measure', 'weight', 'priority'])->all(), 'created_by' => $request->user()->id, 'evaluation_cycle_id' => $cycle->id, 'start_date' => $cycle->periodStart()->toDateString(), 'deadline' => $cycle->periodEnd()->toDateString(), 'description' => $values['justification']]);
                $target->offices()->attach($office->id, ['allocated_target_value' => $values['target_value']]);
                app(\App\Services\TargetHierarchy::class)->linkAvailableUpcrs($target);
                $values['approved_office_target_id'] = $target->id;
                $target->notifyAssignedSupervisors([$office->id]);
            }
            $proposal->update(['state' => match ($data['decision']) { 'approve' => 'accepted', 'return' => 'returned', default => 'rejected' }, 'proposal' => $values, 'review_notes' => $data['review_notes']]);
            WorkflowAudit::record($proposal, 'Office target proposal reviewed', null, $proposal->toArray());
            User::find($proposal->submitted_by)?->notify(new TargetProposalNotification($proposal->id, 'Your office target was '.$proposal->state.'. '.$data['review_notes'], route('office-target-proposals.index', [], false)));
        });
        return back()->with('status', 'Target review recorded.');
    }
}
