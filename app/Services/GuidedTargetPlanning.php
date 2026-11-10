<?php

namespace App\Services;

use App\Models\CommitmentPlan;
use App\Models\Office;
use App\Models\PerformanceTarget;
use App\Models\TargetDocumentDraft;
use App\Models\TargetProposal;
use App\Models\TargetScopeDraft;
use App\Models\User;
use App\Notifications\TargetProposalNotification;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class GuidedTargetPlanning
{
    public function __construct(private CommitmentPlanning $planning) {}

    public function normalize(CommitmentPlan $plan, array $data): array
    {
        abort_unless($plan->level === 'opcr' && ! $plan->parent_id, 422, 'Set scope on the root OPCR; campus plans fetch their parent targets.');
        if ($plan->office->organization_scope === 'campus' && $data['coverage'] === 'university') {
            $this->fail('A Campus OPCR covers its own campus or selected departments. Use a university-based period for university-wide coverage.');
        }
        $ids = array_values(array_unique(array_map('intval', $data['recipient_ids'])));
        $campuses = array_values(array_unique(array_map('intval', $data['campus_ids'] ?? [])));
        $recipients = Office::whereIn('id', $ids)->get();
        if ($recipients->count() !== count($ids)) {
            $this->fail('Choose existing responsible departments.');
        }
        foreach ($recipients as $office) {
            $this->planning->organization->verify($office);
            if ($office->planning_level !== 'division' || ! in_array($plan->office_id, $this->planning->organization->ancestors($office), true)) {
                $this->fail('Choose verified departments beneath this OPCR.');
            }
            if ($plan->cycle->multi_campus_enabled && ($office->organization_scope !== 'campus' || ! in_array($office->campus_id, $campuses, true))) {
                $this->fail('Each responsible department must belong to a selected campus.');
            }
        }
        if ($plan->cycle->multi_campus_enabled) {
            $actual = $recipients->pluck('campus_id')->unique()->sort()->values()->all();
            sort($campuses);
            if ($actual !== $campuses) {
                $this->fail('Select responsible departments for each selected campus. Coverage alone does not assign work.');
            }
            if ($data['coverage'] === 'campus' && count($campuses) !== 1) {
                $this->fail('Campus coverage requires exactly one campus.');
            }
        }
        if ($data['quantity_basis'] === 'per_department' && $data['measurement'] !== 'count') {
            $this->fail('Per-department quantities apply only to divisible counts. Rates and shared outputs preserve their original measure.');
        }
        $entered = (float) $data['quantity'];
        if (($data['whole_numbers'] ?? true) && $data['measurement'] === 'count' && floor($entered) !== $entered) {
            $this->fail('Enter a whole number for this count.');
        }
        if ($data['measurement'] === 'rate' && $entered > 100) {
            $this->fail('Percentage standards cannot exceed 100%.');
        }
        $total = $data['quantity_basis'] === 'per_department' ? $entered * count($ids) : $entered;
        if ($total <= 0 || $total > 9999999999.99) {
            $this->fail('The calculated total is outside the supported range.');
        }
        $metadata = ['coverage' => $data['coverage'], 'campus_ids' => $campuses, 'recipient_ids' => $ids,
            'entered_quantity' => $entered, 'quantity_basis' => $data['quantity_basis'], 'matching_reason' => $data['matching_reason']];
        unset($data['coverage'], $data['campus_ids'], $data['recipient_ids'], $data['quantity_basis'], $data['matching_reason']);

        return [...$data, 'quantity' => $total, 'scope_metadata' => $metadata];
    }

    public function confirm(TargetScopeDraft $draft, User $actor): PerformanceTarget
    {
        return DB::transaction(function () use ($draft, $actor) {
            $this->planning->lockRoot($draft->plan);
            $draft = TargetScopeDraft::whereKey($draft->id)->lockForUpdate()->firstOrFail();
            abort_unless($draft->created_by === $actor->id && $this->planning->organization->canPrepare($actor, $draft->plan), 403);
            if ($draft->performance_target_id) {
                return PerformanceTarget::findOrFail($draft->performance_target_id);
            }
            abort_unless($draft->plan_revision === $draft->plan->revision, 422, 'This draft belongs to an earlier revision. Start a new draft.');
            $data = $this->normalize($draft->plan, $draft->payload);
            $data['scope_metadata'] += ['confirmed_by' => $actor->id, 'confirmed_at' => now()->toIso8601String()];
            $document = $draft->documentDraft;
            if ($document) {
                $document = TargetDocumentDraft::whereKey($document->id)->lockForUpdate()->firstOrFail();
                abort_if($document->performance_target_id, 422, 'This extracted target has already been imported.');
                abort_unless($document->document->evaluation_cycle_id === $draft->plan->evaluation_cycle_id && ($actor->hasRole('Administrator') || $document->document->created_by === $actor->id), 403);
                $data['source_evidence'] = 'Document #'.$document->target_document_id.' / draft #'.$document->id.': '.$document->proposal['source_excerpt'];
            }
            $proposal = $draft->proposal;
            if ($proposal) {
                $proposal = TargetProposal::whereKey($proposal->id)->lockForUpdate()->firstOrFail();
                abort_unless($proposal->state === 'pending' && $proposal->submitted_by !== $actor->id && $proposal->review_office_id === $draft->plan->office_id && $proposal->evaluation_cycle_id === $draft->plan->evaluation_cycle_id && $this->planning->organization->isOwner($actor, $draft->plan->office), 403);
            }
            if ($proposal?->amends_target_id) {
                $old = PerformanceTarget::findOrFail($proposal->amends_target_id);
                abort_unless($old->commitment_plan_id === $draft->plan->id && $old->plan_revision < $draft->plan->revision, 422, 'Open a reviewed amendment before changing an approved target.');
                $this->planning->writable($draft->plan);
                abort_unless($old->measurement === $data['measurement'] && $old->unit === $data['unit'], 422, 'Preserve the approved measurement and unit.');
                $target = $draft->plan->targets()->where('root_target_id', $old->root_target_id)->firstOrFail();
                $target->update($data);
                DB::table('target_eligibilities')->where('performance_target_id', $target->id)->whereNull('employee_id')->delete();
                $this->syncEligibility($target, $actor);
            } else {
                $target = $this->planning->addTarget($draft->plan, $actor, $data);
            }
            $proposal?->update(['state' => 'accepted', 'root_plan_id' => $draft->plan->id, 'result_target_id' => $target->id]);
            if ($proposal) {
                User::find($proposal->submitted_by)?->notify(new TargetProposalNotification($proposal->id, 'Your target proposal was accepted into an OPCR draft for commitment review.'));
            }
            $draft->update(['performance_target_id' => $target->id]);
            $document?->forceFill(['performance_target_id' => $target->id])->save();
            $this->planning->event($draft->plan, $actor, 'Target scope confirmed', ['target_id' => $target->id, 'scope' => $data['scope_metadata']]);

            return $target;
        });
    }

    public function syncEligibility(PerformanceTarget $target, User $actor): void
    {
        if (! $target->scope_metadata) {
            return;
        }
        foreach (Office::where('parent_office_id', $target->plan->office_id)->get() as $child) {
            $relevant = Office::whereIn('id', $target->scope_metadata['recipient_ids'])->get()->contains(fn ($recipient) => in_array($child->id, $this->planning->organization->ancestors($recipient), true));
            if (! $relevant) {
                continue;
            }
            DB::table('target_eligibilities')->updateOrInsert(['performance_target_id' => $target->id, 'office_id' => $child->id, 'employee_id' => null],
                ['reason' => $target->scope_metadata['matching_reason'], 'confirmed_by' => $actor->id, 'created_at' => now(), 'updated_at' => now()]);
        }
    }

    public function assertDepartmentShare(PerformanceTarget $source, CommitmentPlan $destination, float $quantity): void
    {
        $scope = $source->scope_metadata;
        if (! $scope || $scope['quantity_basis'] !== 'per_department') {
            return;
        }
        $recipients = Office::whereIn('id', $scope['recipient_ids'])->get();
        $branchCount = $recipients->filter(fn ($o) => in_array($destination->office_id, $this->planning->organization->ancestors($o), true))->count();
        if ($branchCount > 0 && $quantity > round($scope['entered_quantity'] * $branchCount, 2)) {
            $this->fail('This quantity exceeds the confirmed per-department share for this branch.');
        }
    }

    private function fail(string $message): never
    {
        throw ValidationException::withMessages(['scope' => $message]);
    }
}
