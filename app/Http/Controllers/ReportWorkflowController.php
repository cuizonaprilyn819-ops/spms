<?php

namespace App\Http\Controllers;

use App\Models\Dpcr;
use App\Models\Ipcr;
use App\Models\Office;
use App\Models\Opcr;
use App\Models\Upcr;
use App\Models\User;
use App\Notifications\ReportWorkflowNotification;
use App\Services\CommitmentPlanning;
use App\Services\PcrCompliance;
use App\Services\PlanningOrganization;
use App\Services\TargetConflictReview;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\View\View;

class ReportWorkflowController extends Controller
{
    private const MODELS = ['opcr' => Opcr::class, 'dpcr' => Dpcr::class, 'upcr' => Upcr::class, 'ipcr' => Ipcr::class];

    public function edit(Request $request, string $type, int $report): View
    {
        $form = $this->report($type, $report);
        abort_unless($form->isEditableBy($request->user()->id), 403);

        $targetContext = app(\App\Services\ReportTargetContext::class)->display($form);
        return view('reports.edit', compact('form', 'type', 'targetContext'));
    }

    public function update(Request $request, string $type, int $report): RedirectResponse
    {
        $form = $this->report($type, $report);
        abort_if($form->commitmentPlan, 422, 'Edit commitments through Commitment Planning; approved revisions are preserved.');
        abort_unless($form->isEditableBy($request->user()->id), 403);
        $data = $request->validate([
            'title' => ['required', 'string', 'max:255'],
            'strategic_program' => ['sometimes', 'nullable', 'string', 'max:5000'],
            'program_outcome' => ['sometimes', 'nullable', 'string', 'max:5000'],
            'success_indicators' => ['nullable', 'string', 'max:5000'],
            'targets' => ['nullable', 'string', 'max:5000'],
        ]);
        DB::transaction(function () use ($form, $data, $request) {
            $locked = $form->newQuery()->whereKey($form->id)->lockForUpdate()->firstOrFail();
            abort_unless($locked->isEditableBy($request->user()->id), 403);
            $context = app(\App\Services\ReportTargetContext::class)->display($locked);
            $source = app(\App\Services\ReportTargetContext::class)->source($locked);
            foreach (['strategic_program', 'program_outcome'] as $field) if (array_key_exists($field, $data)) $context[$field] = $data[$field];
            $before = $locked->toArray();
            $locked->fill(collect($data)->except(['strategic_program', 'program_outcome'])->all());
            $locked->forceFill(['target_context' => json_encode([...$context, 'source' => $source], JSON_THROW_ON_ERROR)])->save();
            \App\Models\WorkflowAudit::record($locked, 'Report target wording reviewed and edited', $before, $locked->toArray());
        });

        return back()->with('status', strtoupper($type).' changes saved.');
    }

    public function paraphrase(Request $request, string $type, int $report, \App\Services\TargetDocumentAi $ai): RedirectResponse
    {
        $form = $this->report($type, $report);
        abort_if($form->commitmentPlan, 422, 'Edit commitments through Commitment Planning.');
        abort_unless($form->isEditableBy($request->user()->id), 403);
        $source = app(\App\Services\ReportTargetContext::class)->source($form);
        // Manual reports may supply missing source labels through the edit form first.
        $context = app(\App\Services\ReportTargetContext::class)->display($form);
        foreach (['strategic_program', 'program_outcome'] as $field) if (! trim($source[$field] ?? '')) $source[$field] = $context[$field] ?? null;
        try {
            $wording = $ai->reportWording($source);
            DB::transaction(function () use ($form, $source, $wording, $request) {
                $locked = $form->newQuery()->whereKey($form->id)->lockForUpdate()->firstOrFail();
                abort_unless($locked->isEditableBy($request->user()->id), 403);
                abort_if($locked->updated_at->ne($form->updated_at), 409, 'The report changed during AI preparation. Reload and retry.');
                $before = $locked->toArray();
                $locked->forceFill(['success_indicators' => $wording['success_indicators'], 'targets' => $wording['success_indicators'], 'target_context' => json_encode([...$wording, 'source' => $source, 'paraphrased_at' => now()->toIso8601String()], JSON_THROW_ON_ERROR)])->save();
                \App\Models\WorkflowAudit::record($locked, 'AI report wording prepared for review', $before, $locked->toArray());
            });
        } catch (\RuntimeException $exception) {
            return back()->withErrors(['paraphrase' => $exception->getMessage()]);
        }
        return back()->with('status', 'Program, outcome and success indicator paraphrased. Review all wording against the original source before submission.');
    }

    public function submit(Request $request, string $type, int $report): RedirectResponse
    {
        $form = $this->report($type, $report);
        if ($plan = $form->commitmentPlan) {
            abort_unless(app(PlanningOrganization::class)->canView($request->user(), $plan), 403);
            abort_unless(app(PlanningOrganization::class)->canPrepare($request->user(), $plan), 403);
            abort_unless($plan->state === 'approved' && ! $plan->targets()->where('needs_reconciliation', true)->exists(), 422, 'Approve and reconcile commitments before submitting the performance report.');
        }
        abort_unless($form->isEditableBy($request->user()->id), 403);
        $from = $form->status;
        $to = $from === $form::STATUS_RETURNED ? $form::STATUS_RESUBMITTED : $form::STATUS_SUBMITTED;
        $this->transition($form, $to, $request->user()->id, null, [
            'submitted_at' => now(),
            'resubmission_count' => $from === $form::STATUS_RETURNED ? $form->resubmission_count + 1 : $form->resubmission_count,
        ]);
        $this->reviewers($form)->each->notify(new ReportWorkflowNotification(strtoupper($type), $form, $to));
        if (config('spms.target_assistance.enabled') && ! $form->commitmentPlan) {
            DB::afterCommit(fn () => app(TargetConflictReview::class)->scan($form->fresh(), $request->user(), true, true));
        }

        return back()->with('status', strtoupper($type).' submitted for review.');
    }

    public function returnForCorrection(Request $request, string $type, int $report): RedirectResponse
    {
        $form = $this->report($type, $report);
        $this->authorizeReviewer($request, $form);
        abort_unless(in_array($form->status, [$form::STATUS_SUBMITTED, $form::STATUS_RESUBMITTED], true), 422);
        $data = $request->validate(['remarks' => ['required', 'string', 'max:5000']]);
        $this->transition($form, $form::STATUS_RETURNED, $request->user()->id, $data['remarks'], ['returned_at' => now()]);
        $form->preparedBy?->notify(new ReportWorkflowNotification(strtoupper($type), $form, 'returned for correction', $data['remarks']));

        return back()->with('status', strtoupper($type).' returned to the employee for correction.');
    }

    public function approve(Request $request, string $type, int $report): RedirectResponse
    {
        $form = $this->report($type, $report);
        $this->authorizeReviewer($request, $form);
        if ($plan = $form->commitmentPlan) {
            foreach ($plan->targets as $target) {
                $summary = app(CommitmentPlanning::class)->summary($target);
                abort_if($summary['outputs']->whereNull('validated_at')->isNotEmpty() || ! $target->evaluations()->exists(), 422, 'Validate supporting outputs and record reviewer ratings before final approval.');
            }
        }
        abort_unless(in_array($form->status, [$form::STATUS_SUBMITTED, $form::STATUS_RESUBMITTED], true), 422);
        $ratings = [];
        $pcrCycle = app(PcrCompliance::class)->cycle($form);
        if ($form instanceof Ipcr && ($form->office_target_id || $pcrCycle?->pcr_submission_due_at)) {
            abort_if((int) $form->employee_id === (int) $request->user()->id, 403, 'You cannot rate your own IPCR.');
            $ratings = $request->validate([
                'quality_rating' => ['required', 'numeric', 'min:0', 'max:5'],
                'efficiency_rating' => ['required', 'numeric', 'min:0', 'max:5'],
                'timeliness_rating' => ['required', 'numeric', 'min:0', 'max:5'],
                'rating_remarks' => ['nullable', 'string', 'max:5000'],
            ]);
            $assignment = DB::table('office_target_employee')->where('office_target_id', $form->office_target_id)->where('user_id', $form->employee_id)->first();
            $actual = $assignment ? (float) ($assignment->completed_value ?? ($assignment->completed_at ? $assignment->allocated_target_value : 0)) : 0;
            if ($form->office_target_id && $actual <= 0) {
                $ratings['quality_rating'] = $ratings['efficiency_rating'] = $ratings['timeliness_rating'] = 0;
            }
            $ratings['average_rating'] = round(((float) $ratings['quality_rating'] + (float) $ratings['efficiency_rating'] + (float) $ratings['timeliness_rating']) / 3, 2);
        }
        $this->transition($form, $form::STATUS_APPROVED, $request->user()->id, null, ['approved_by' => $request->user()->id, 'approved_at' => now(), ...$ratings]);
        $form->preparedBy?->notify(new ReportWorkflowNotification(strtoupper($type), $form, 'approved'));
        if ($form instanceof Ipcr && (int) $form->employee_id !== (int) $form->prepared_by) {
            $form->employee?->notify(new ReportWorkflowNotification('IPCR', $form, 'approved'));
        }

        return back()->with('status', strtoupper($type).' approved successfully.');
    }

    private function report(string $type, int $id): Model
    {
        abort_unless(isset(self::MODELS[$type]), 404);

        return self::MODELS[$type]::with(['preparedBy', 'statusHistories.actor'])->findOrFail($id);
    }

    private function transition(Model $form, string $to, int $actor, ?string $remarks, array $extra = []): void
    {
        DB::transaction(function () use ($form, $to, $actor, $remarks, $extra): void {
            $current = $form->newQuery()->whereKey($form->id)->lockForUpdate()->firstOrFail();
            if (in_array($to, ['submitted', 'resubmitted'])) {
                abort_unless($current->isEditableBy($actor), 403);
                app(\App\Services\ReportEvidence::class)->ensureReady($current);
                $extra['submitted_at'] = now();
            } elseif (in_array($to, ['approved', 'returned'])) {
                abort_unless(in_array($current->status, ['submitted', 'resubmitted']), 422);
            }
            $from = $current->status;
            $form->setRawAttributes($current->getAttributes(), true);
            $form->update(['status' => $to, ...$extra]);
            if (in_array($to, ['submitted', 'resubmitted'])) {
                app(PcrCompliance::class)->recordSubmission($form);
            }
            $form->statusHistories()->create(['from_status' => $from, 'to_status' => $to, 'remarks' => $remarks, 'acted_by' => $actor]);
        });
    }

    private function authorizeReviewer(Request $request, Model $form): void
    {
        $user = $request->user();
        abort_if((int) $form->prepared_by === $user->id || (int) $form->employee_id === $user->id, 403, 'You cannot review your own report.');
        if ($plan = $form->commitmentPlan) {
            abort_unless(app(PlanningOrganization::class)->canView($user, $plan), 403);
            abort_unless(($user->hasRole('Planning Officer') || $plan->reviewer_id === $user->id) && $plan->prepared_by !== $user->id && $plan->employee_id !== $user->id, 403);
            abort_unless($plan->state === 'approved' && ! $plan->targets()->where('needs_reconciliation', true)->exists(), 422, 'Commitments need approval or reconciliation.');

            return;
        }
        abort_unless($user->hasAnyRole(['Administrator', 'Planning Officer', 'HR Head', 'Campus Director', 'Supervisor', 'Division Head', 'Unit Head']), 403);
        if ($user->hasAnyRole(['Supervisor', 'Division Head', 'Unit Head'])) {
            $office = Office::find($this->officeId($form));
            $reviewOfficeId = $office && (int) $office->head_id === (int) $form->prepared_by ? $office->parent_office_id : $office?->id;
            abort_unless($user->office_id && (int) $user->office_id === (int) $reviewOfficeId, 403);
        }
        if ($user->hasRole('Campus Director') && $user->office?->campus) {
            abort_unless($form->preparedBy?->office?->campus === $user->office->campus, 403);
        }
    }

    private function reviewers(Model $form)
    {
        $office = Office::find($this->officeId($form));
        $officeId = $office && (int) $office->head_id === (int) $form->prepared_by ? $office->parent_office_id : $office?->id;
        $reportingOfficeIds = $office ? app(PlanningOrganization::class)->ancestors($office) : [];
        $reportingHeadIds = \App\Models\Office::whereIn('id', $reportingOfficeIds)->whereNotNull('head_id')->pluck('head_id')->all();
        $assignedReviewerId = $form->commitmentPlan?->reviewer_id;

        return User::query()->whereNotIn('id', array_filter([$form->prepared_by, $form->employee_id]))->where(function ($query) use ($officeId, $reportingOfficeIds, $reportingHeadIds, $assignedReviewerId): void {
            $query->where(fn ($q) => $q->whereHas('roles', fn ($r) => $r->whereIn('name', ['Supervisor', 'Division Head', 'Unit Head']))->where('office_id', $officeId));
            $query->orWhere(fn ($q) => $q->whereHas('roles', fn ($r) => $r->where('name', 'Campus Director'))
                ->where(fn ($offices) => $offices->whereIn('office_id', $reportingOfficeIds)->orWhereIn('id', $reportingHeadIds)));
            $query->orWhereHas('roles', fn ($r) => $r->whereIn('name', ['HR Head', 'Planning Officer']));
            if ($assignedReviewerId) $query->orWhere('users.id', $assignedReviewerId);
        })->get()->filter(fn ($person) => ! $form->commitmentPlan || app(PlanningOrganization::class)->canView($person, $form->commitmentPlan));
    }

    private function officeId(Model $form): ?int
    {
        return match (true) {
            $form instanceof Opcr => $form->office_id,
            $form instanceof Dpcr => $form->employee?->office_id,
            $form instanceof Upcr => $form->employee?->office_id,
            $form instanceof Ipcr => $form->employee?->office_id,
        };
    }
}
