<?php

namespace App\Http\Controllers;

use App\Models\Campus;
use App\Models\CommitmentPlan;
use App\Models\Office;
use App\Models\TargetDocumentDraft;
use App\Models\TargetProposal;
use App\Models\TargetScopeDraft;
use App\Services\AiAssistant;
use App\Services\CommitmentPlanning;
use App\Services\GuidedTargetPlanning;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class GuidedTargetController extends Controller
{
    public function __construct(private CommitmentPlanning $planning, private GuidedTargetPlanning $guided) {}

    private function manage(Request $r, CommitmentPlan $plan): void
    {
        abort_unless($this->planning->organization->canPrepare($r->user(), $plan), 403);
        $this->planning->writable($plan);
        abort_unless($plan->level === 'opcr' && ! $plan->parent_id, 422);
    }

    public function create(Request $r, CommitmentPlan $plan)
    {
        $this->manage($r, $plan);
        $document = null;
        $proposal = null;
        if ($r->filled('proposal_id')) {
            $proposal = TargetProposal::findOrFail($r->integer('proposal_id'));
            $this->proposalAccess($r, $plan, $proposal);
        }
        if ($r->filled('document_draft_id')) {
            $document = TargetDocumentDraft::findOrFail($r->integer('document_draft_id'));
            abort_unless(! $document->performance_target_id && $document->document->evaluation_cycle_id === $plan->evaluation_cycle_id && ($r->user()->hasRole('Administrator') || $document->document->created_by === $r->user()->id), 403);
        }

        return $this->page($plan, null, $document, $proposal);
    }

    private function page(CommitmentPlan $plan, ?TargetScopeDraft $draft, ?TargetDocumentDraft $document = null, ?TargetProposal $proposal = null)
    {
        $offices = Office::where('planning_level', 'division')->with('campusRecord')->get()->filter(fn ($o) => in_array($plan->office_id, $this->planning->organization->ancestors($o), true));

        return view('planning.scope', ['plan' => $plan, 'draft' => $draft, 'document' => $document ?? $draft?->documentDraft, 'proposal' => $proposal ?? $draft?->proposal,
            'offices' => $offices, 'campuses' => Campus::where('is_active', true)->when($plan->office->organization_scope === 'campus', fn ($q) => $q->whereKey($plan->office->campus_id))->get(),
            'preview' => $draft ? $this->guided->normalize($plan, $draft->payload) : null]);
    }

    public function show(Request $r, TargetScopeDraft $scopeDraft)
    {
        abort_unless($scopeDraft->created_by === $r->user()->id, 403);
        $this->manage($r, $scopeDraft->plan);
        abort_unless($scopeDraft->plan_revision === $scopeDraft->plan->revision, 422);

        return $this->page($scopeDraft->plan, $scopeDraft);
    }

    public function store(Request $r, CommitmentPlan $plan)
    {
        $this->manage($r, $plan);
        $data = $r->validate(CommitmentPlanningController::targetRules() + self::scopeRules());
        $this->guided->normalize($plan, $data);
        $document = $r->filled('document_draft_id') ? TargetDocumentDraft::findOrFail($r->integer('document_draft_id')) : null;
        $proposal = $r->filled('proposal_id') ? TargetProposal::findOrFail($r->integer('proposal_id')) : null;
        if ($proposal) {
            $this->proposalAccess($r, $plan, $proposal);
        }
        abort_if($proposal && $document, 422);
        if ($document) {
            abort_unless(! $document->performance_target_id && $document->document->evaluation_cycle_id === $plan->evaluation_cycle_id && ($r->user()->hasRole('Administrator') || $document->document->created_by === $r->user()->id), 403);
        }
        $draft = TargetScopeDraft::create(['commitment_plan_id' => $plan->id, 'plan_revision' => $plan->revision, 'created_by' => $r->user()->id, 'document_draft_id' => $document?->id, 'proposal_id' => $proposal?->id, 'payload' => $data]);

        return redirect()->route('planning.scope-show', $draft)->with('status', 'Private draft saved. Review the calculated total before confirming.');
    }

    public static function scopeRules(): array
    {
        return [
            'coverage' => ['required', Rule::in(['departments', 'campus', 'university'])],
            'campus_ids' => ['sometimes', 'array', 'max:100'], 'campus_ids.*' => ['integer', Rule::exists('campuses', 'id')->where('is_active', true)],
            'recipient_ids' => ['required', 'array', 'min:1', 'max:100'], 'recipient_ids.*' => ['integer', 'distinct', Rule::exists('offices', 'id')],
            'quantity_basis' => ['required', Rule::in(['total', 'per_department'])], 'matching_reason' => ['required', 'string', 'max:5000'],
        ];
    }

    private function proposalAccess(Request $r, CommitmentPlan $plan, TargetProposal $proposal): void
    {
        abort_unless($proposal->state === 'pending' && $proposal->evaluation_cycle_id === $plan->evaluation_cycle_id && $proposal->review_office_id === $plan->office_id && $proposal->submitted_by !== $r->user()->id && $this->planning->organization->isOwner($r->user(), $plan->office), 403);
    }

    public function confirm(Request $r, TargetScopeDraft $scopeDraft)
    {
        $r->validate(['confirmed' => ['accepted']]);
        $target = $this->guided->confirm($scopeDraft, $r->user());

        return redirect()->route('planning.show', $target->plan)->with('status', 'Target scope confirmed in the OPCR draft. Commitment approval is still required.');
    }

    public function suggest(Request $r, TargetScopeDraft $scopeDraft, AiAssistant $ai)
    {
        abort_unless($scopeDraft->created_by === $r->user()->id, 403);
        $this->manage($r, $scopeDraft->plan);
        abort_unless($scopeDraft->plan_revision === $scopeDraft->plan->revision && ! $scopeDraft->performance_target_id, 422);
        $offices = Office::where('planning_level', 'division')->get()->filter(fn ($o) => $o->hierarchy_verified_at && in_array($scopeDraft->plan->office_id, $this->planning->organization->ancestors($o), true));
        try {
            $advice = $ai->draft('scope', ['target' => $scopeDraft->payload, 'departments' => $offices->map(fn ($o) => ['id' => $o->id, 'name' => $o->name, 'mandate' => $o->mandate])->values()->all()]);
        } catch (\RuntimeException $e) {
            return back()->withErrors(['ai' => 'AI assistance is unavailable. You can review and confirm the target manually.']);
        }
        $scopeDraft->update(['ai_advice' => $advice]);

        return back()->with('status', 'AI advice saved. Recipient selections and quantities remain yours to confirm.');
    }
}
