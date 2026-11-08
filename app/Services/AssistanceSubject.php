<?php

namespace App\Services;

use App\Models\Accomplishment;
use App\Models\CommitmentPlan;
use App\Models\Dpcr;
use App\Models\HistoricalPcr;
use App\Models\Ipcr;
use App\Models\Mov;
use App\Models\Office;
use App\Models\OfficeTarget;
use App\Models\Opcr;
use App\Models\PerformanceTarget;
use App\Models\PlanningOutput;
use App\Models\TargetDocumentDraft;
use App\Models\TargetMov;
use App\Models\TargetProposal;
use App\Models\TargetScopeDraft;
use App\Models\Upcr;
use App\Models\User;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Gate;

/** Allowlisted adapters; assistance never grants permission to an underlying record. */
class AssistanceSubject
{
    public const TYPES = [
        'plan' => CommitmentPlan::class, 'target' => PerformanceTarget::class,
        'scope' => TargetScopeDraft::class, 'proposal' => TargetProposal::class,
        'office-target' => OfficeTarget::class, 'document-draft' => TargetDocumentDraft::class,
        'historical' => HistoricalPcr::class, 'accomplishment' => Accomplishment::class,
        'mov' => Mov::class, 'target-mov' => TargetMov::class, 'planning-output' => PlanningOutput::class,
        'opcr' => Opcr::class, 'dpcr' => Dpcr::class,
        'upcr' => Upcr::class, 'ipcr' => Ipcr::class,
    ];

    public function resolve(string $kind, int $id): Model
    {
        $class = self::TYPES[$kind] ?? abort(404);

        return $class::findOrFail($id);
    }

    public function canonical(Model $m): Model
    {
        $m = $m->exists ? $m->fresh() : $m;
        if ($m instanceof PerformanceTarget && $m->plan) {
            return $m->plan;
        }
        if (in_array(get_class($m), PcrCompliance::REPORTS, true) && $m->commitmentPlan) {
            return $m->commitmentPlan;
        }
        if ($m instanceof OfficeTarget && $m->performance_target_id) {
            return $m->performanceTarget?->plan ?? $m;
        }

        return $m;
    }

    public function kind(Model $m): string
    {
        return array_search(get_class($m), self::TYPES, true) ?: abort(404);
    }

    public function fingerprint(Model $m): string
    {
        $m = $m->fresh() ?? $m;
        $data = $m->getAttributes();
        foreach (['updated_at', 'state', 'status', 'approved_at', 'approved_by', 'approved_revision', 'agreed_at', 'submitted_at', 'returned_at', 'resubmission_count'] as $key) {
            unset($data[$key]);
        }
        if ($m instanceof CommitmentPlan) {
            $data['lines'] = $m->targets()->orderBy('id')->get()->map->getAttributes()->all();
        }
        if ($m instanceof HistoricalPcr) {
            $data['archive_revision'] = $m->revision?->getAttributes();
        }
        if (in_array($this->kind($m), ['mov', 'target-mov', 'planning-output'], true)) {
            $data['analysis'] = $m->analysis?->getAttributes();
        }

        return hash('sha256', json_encode($data, JSON_THROW_ON_ERROR));
    }

    public function officeId(Model $m): ?int
    {
        if ($m instanceof PerformanceTarget) {
            return $m->plan?->office_id ?? $this->officeId($m->targetable);
        }

        return $m->office_id ?? $m->employee?->office_id ?? $m->preparedBy?->office_id ?? $m->createdBy?->office_id;
    }

    public function cycleId(Model $m): ?int
    {
        if ($m instanceof PerformanceTarget) {
            return $m->plan?->evaluation_cycle_id ?? app(PcrCompliance::class)->cycle($m->targetable)?->id;
        }
        if (in_array(get_class($m), PcrCompliance::REPORTS, true)) {
            return app(PcrCompliance::class)->cycle($m)?->id;
        }
        if ($m instanceof TargetDocumentDraft) {
            return $m->document->evaluation_cycle_id;
        }
        if ($m instanceof TargetScopeDraft) {
            return $m->plan->evaluation_cycle_id;
        }

        return $m->evaluation_cycle_id;
    }

    public function ownerIds(Model $m): array
    {
        if ($m instanceof PerformanceTarget) {
            return $this->ownerIds($m->plan ?? $m->targetable);
        }
        if ($m instanceof TargetScopeDraft) {
            return [(int) $m->created_by];
        }
        if ($m instanceof TargetDocumentDraft) {
            return [(int) $m->document->created_by];
        }

        return array_values(array_filter(array_unique([$m->prepared_by, $m->employee_id, $m->created_by, $m->submitted_by, $m->owner_id])));
    }

    public function canView(User $u, Model $m): bool
    {
        if (in_array($this->kind($m), ['mov', 'target-mov', 'planning-output'], true)) {
            return (int) ($m->uploaded_by ?? $m->submitted_by ?? $m->user_id) === $u->id;
        }
        if ($m instanceof HistoricalPcr) {
            return Gate::forUser($u)->allows('view', $m);
        }
        if ($m instanceof TargetScopeDraft) {
            return (int) $m->created_by === $u->id && app(PlanningOrganization::class)->canView($u, $m->plan);
        }
        if ($m instanceof TargetDocumentDraft) {
            return $u->hasRole('Administrator') || ((int) $m->document->created_by === $u->id && $u->hasRole('Planning Officer'));
        }
        if ($m instanceof TargetProposal) {
            return (int) $m->submitted_by === $u->id || ($m->review_office_id && app(PlanningOrganization::class)->isOwner($u, Office::find($m->review_office_id))) || ($m->office && app(PlanningOrganization::class)->hasGrant($u, $m->office));
        }
        if ($m instanceof Accomplishment) {
            return (int) $m->submitted_by === $u->id || $this->canView($u, $m->performanceTarget);
        }
        $m = $this->canonical($m);
        if ($m instanceof CommitmentPlan) {
            return app(PlanningOrganization::class)->canView($u, $m);
        }
        if ($m instanceof OfficeTarget) {
            if ($m->evaluationCycle?->multi_campus_enabled && ! $u->hasRole('Administrator')) {
                $ids = app(PlanningOrganization::class)->accessibleOfficeIds($u);

                return $m->offices()->whereIn('offices.id', $ids)->exists() || $m->assignedEmployees()->whereKey($u->id)->exists();
            }

            return app(TargetHierarchy::class)->canView($u, $m);
        }
        if ($u->hasRole('Administrator') || in_array($u->id, $this->ownerIds($m))) {
            return true;
        }
        $office = Office::find($this->officeId($m));

        return $office && (app(PlanningOrganization::class)->hasGrant($u, $office) || in_array($office->id, app(TargetHierarchy::class)->managedOfficeIds($u), true));
    }

    public function canEdit(User $u, Model $m): bool
    {
        if (! $this->canView($u, $m)) {
            return false;
        }
        if (in_array($this->kind($m), ['mov', 'target-mov', 'planning-output'], true)) {
            return $m->analysis?->isReady() && ($m->analysis?->findings['supported'] ?? false) && (! ($m instanceof PlanningOutput) || $m->target?->plan?->report?->status !== 'approved');
        }
        if ($m instanceof HistoricalPcr) {
            return true;
        } // Notes/correction requests only, never transcription fields.
        if ($m instanceof TargetScopeDraft) {
            return ! $m->performance_target_id && $this->canEdit($u, $m->plan);
        }
        if ($m instanceof TargetDocumentDraft) {
            return ! $m->office_target_id && ! $m->performance_target_id;
        }
        if ($m instanceof TargetProposal) {
            return (int) $m->submitted_by === $u->id && in_array($m->state, ['returned', 'rejected', 'pending'], true);
        }
        if ($m instanceof Accomplishment) {
            return (int) $m->submitted_by === $u->id && ! $m->performanceTarget->commitment_plan_id;
        }
        if ($m instanceof PerformanceTarget) {
            return $this->canEdit($u, $m->plan ?? $m->targetable);
        }
        if ($m instanceof CommitmentPlan) {
            return $m->state === 'draft' && $m->cycle->isOpen() && app(PlanningOrganization::class)->canPrepare($u, $m);
        }
        if ($m instanceof OfficeTarget) {
            return ! $m->performance_target_id && $m->evaluationCycle?->isOpen() && ($u->hasRole('Administrator') || ((int) $m->created_by === $u->id && $u->hasAnyRole(['Planning Officer', 'Campus Director'])));
        }

        return ! $m->commitmentPlan && $m->isEditableBy($u->id) && app(PcrCompliance::class)->cycle($m)?->isOpen();
    }

    public function canReview(User $u, Model $m): bool
    {
        $m = $this->canonical($m);
        if (! $this->canView($u, $m) || in_array($u->id, $this->ownerIds($m))) {
            return false;
        }
        if ($m instanceof CommitmentPlan) {
            return (int) $m->reviewer_id === $u->id;
        }
        if ($m instanceof HistoricalPcr) {
            return $u->hasRole('Administrator');
        }
        if ($m instanceof OfficeTarget) {
            return $u->hasRole('Administrator') || $m->offices()->where('planning_reviewer_id', $u->id)->exists();
        }
        if ($m instanceof TargetDocumentDraft || $m instanceof TargetScopeDraft || $m instanceof TargetProposal) {
            return false;
        }
        $office = Office::find($this->officeId($m));

        return $u->hasRole('Administrator') || ($office && ((int) $office->planning_reviewer_id === $u->id || app(PlanningOrganization::class)->hasGrant($u, $office) || ($u->hasAnyRole(['Supervisor', 'Division Head', 'Unit Head', 'HR Head', 'Campus Director']) && in_array($office->id, app(TargetHierarchy::class)->managedOfficeIds($u), true))));
    }

    public function recipients(Model $m)
    {
        $ids = $this->ownerIds($m);
        $office = Office::find($this->officeId($m));
        $ids[] = $m->reviewer_id ?? $office?->planning_reviewer_id;

        return User::whereIn('id', array_filter($ids))->get()->filter(fn ($u) => $this->canView($u, $m));
    }

    public function fields(Model $m): array
    {
        if (in_array($this->kind($m), ['mov', 'target-mov', 'planning-output'], true)) {
            return ['statement'];
        }

        return match (true) {
            $m instanceof HistoricalPcr => ['message'],
            $m instanceof Accomplishment => ['details'],
            $m instanceof PerformanceTarget => $m->plan?->parent_id ? ['local_target'] : ['target', 'success_indicator'],
            $m instanceof OfficeTarget, $m instanceof TargetDocumentDraft => ['title', 'success_indicator'],
            $m instanceof CommitmentPlan => $m->parent_id ? ['local_target', 'details'] : ['target', 'success_indicator', 'details'],
            $m instanceof TargetProposal, $m instanceof TargetScopeDraft => ['target', 'success_indicator'],
            default => ['title', 'strategic_program', 'program_outcome', 'success_indicators', 'targets'],
        };
    }

    public function lines(Model $m): array
    {
        $m = $this->canonical($m);
        if ($m instanceof CommitmentPlan) {
            return $m->targets()->orderBy('id')->get()->map(fn ($t) => ['key' => 'target:'.$t->id, 'root' => $t->root_target_id, 'parent' => $t->parent_target_id, 'wording' => $t->target, 'indicator' => $t->success_indicator, 'quantity' => $t->quantity, 'unit' => $t->unit, 'deadline' => $t->deadline?->toDateString(), 'standards' => $t->standards, 'contributor' => $t->contributes_only, 'mandate' => $m->office?->mandate, 'duties' => $m->employee?->planning_duties])->all();
        }
        if ($m instanceof HistoricalPcr) {
            return [];
        } // Source transcription is never operational target context.
        $payload = ($m instanceof TargetDocumentDraft ? $m->proposal : ($m instanceof TargetScopeDraft ? $m->payload : ($m instanceof TargetProposal ? $m->proposal : []))) ?? [];

        return [['key' => $this->kind($m).':'.$m->id, 'root' => null, 'parent' => null, 'wording' => $payload['target'] ?? $payload['title'] ?? $m->target ?? $m->title ?? '', 'indicator' => $payload['success_indicator'] ?? $m->success_indicator ?? $m->success_indicators ?? $m->targets ?? '', 'quantity' => $payload['quantity'] ?? $m->target_value, 'unit' => $payload['unit'] ?? $payload['unit_of_measure'] ?? $m->unit_of_measure, 'deadline' => $payload['deadline'] ?? $m->deadline?->toDateString(), 'standards' => $payload['standards'] ?? '', 'mandate' => $m->createdBy?->office?->mandate ?? $m->plan?->office?->mandate, 'duties' => null]];
    }
}
