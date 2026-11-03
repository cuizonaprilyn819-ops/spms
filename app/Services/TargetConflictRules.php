<?php

namespace App\Services;

use App\Models\CommitmentPlan;
use App\Models\OfficeTarget;
use App\Models\PerformanceTarget;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class TargetConflictRules
{
    public function inspect(Model $subject): array
    {
        $findings = [];
        $add = function (string $code, string $title, string $why, string $next, bool $blocking = true, array $refs = []) use (&$findings) {
            $findings[] = compact('code', 'title', 'blocking') + ['explanation' => $why, 'recommendation' => $next, 'references' => $refs];
        };
        if ($subject instanceof CommitmentPlan) {
            $planning = app(CommitmentPlanning::class);
            $lines = $subject->targets()->with('parentTarget.plan')->get();
            foreach ($lines->groupBy('root_target_id') as $group) {
                if ($group->count() > 1) {
                    $add('duplicate_lineage', 'Duplicate source lines', 'This plan contains more than one line for the same OPCR source.', 'Keep one current line per source lineage.');
                }
            }
            foreach ($lines as $target) {
                $refs = ['target:'.$target->id];
                if ($target->needs_reconciliation) {
                    $add('source_revision', 'Parent revision needs reconciliation', 'This line was retained from an earlier parent commitment.', 'Reconcile the line through a draft or amendment.', true, $refs);
                }
                $parent = $target->parentTarget;
                if ($subject->parent_id) {
                    if (! $parent || $parent->commitment_plan_id !== $subject->parent_id || $parent->plan_revision !== $subject->parent->revision || $target->source_revision !== $subject->parent->revision) {
                        $add('source_revision', 'Source revision is outdated', 'The selected source is not the current immediate parent revision.', 'Refresh and reconcile the source.', true, $refs);

                        continue;
                    }
                    if (! $planning->eligible($parent, $subject)) {
                        $add('eligibility', 'Recipient relevance is not confirmed', 'The configured eligibility does not cover this office or person.', 'Ask the parent head to verify the duty and eligibility.', true, $refs);
                    }
                    if ($target->deadline && $parent->deadline && $target->deadline->gt($parent->deadline)) {
                        $add('deadline', 'Child deadline is later than its parent', 'The child work finishes after the commitment it supports.', 'Correct the child deadline or request a parent amendment.', true, $refs);
                    }
                    if ($parent->measurement === 'count') {
                        try {
                            $planning->allocation->assertAvailable((float) $parent->quantity, $planning->reviewedShares($parent, $subject->id), (float) $target->quantity);
                            app(GuidedTargetPlanning::class)->assertDepartmentShare($parent, $subject, (float) $target->quantity);
                        } catch (ValidationException $e) {
                            $add('allocation', 'Allocation exceeds available responsibility', collect($e->errors())->flatten()->join(' '), 'Revise the share or reconcile the parent commitment.', true, $refs);
                        }
                        $proposed = PerformanceTarget::where('parent_target_id', $parent->id)->whereHas('plan', fn ($q) => $q->whereIn('state', ['submitted', 'endorsed', 'approved']))->with('plan')->get()->filter(fn ($line) => $line->plan_revision === $line->plan->revision && ! $line->contributes_only && $line->commitment_plan_id !== $subject->id)->sum('quantity');
                        try {
                            $planning->allocation->assertAvailable((float) $parent->quantity, max((float) $proposed, $planning->reviewedShares($parent, $subject->id)), (float) $target->quantity);
                        } catch (ValidationException $e) {
                            $add('proposed_allocation', 'Submitted shares compete for the same quantity', 'The current share and other submitted or confirmed shares exceed their immediate parent quantity.', 'Coordinate the office shares before approval. Drafts do not reserve responsibility.', true, $refs);
                        }
                        $driver = DB::table('planning_drivers')->where('performance_target_id', $parent->id)->where('office_id', $subject->office_id)->where('employee_id', $subject->employee_id)->whereDate('valid_until', '>=', today())->first();
                        if ($driver?->capacity !== null && (float) $target->quantity > (float) $driver->capacity) {
                            $add('capacity', 'Recorded capacity is exceeded', 'The proposed share exceeds the current recorded capacity.', 'Review capacity inputs and quantity with the responsible head.', true, $refs);
                        }
                        if (! $driver && empty($target->allocation_basis['override_reason'])) {
                            $add('needs_information', 'Needs information about allocation', 'No current workload driver or manual allocation reason was recorded.', 'Record workload inputs or explain the manually selected share.', false, $refs);
                        }
                    }
                }
                if ($target->measurement === 'count' && $planning->reviewedShares($target) > (float) $target->quantity) {
                    $add('allocation', 'Confirmed descendants exceed this quantity', 'Existing confirmed shares exceed the revised parent commitment.', 'Reconcile allocations before approval.', true, $refs);
                }
            }
            if (! trim($subject->office?->mandate ?? '') || ($subject->employee_id && ! trim($subject->employee?->planning_duties ?? ''))) {
                $add('needs_information', 'Needs information about duties', 'The recorded mandate or employee duties are incomplete.', 'Have the authorized head complete and verify the duties.', false);
            }
        }
        if ($subject instanceof OfficeTarget) {
            $total = $subject->offices()->get()->sum(fn ($o) => (float) $o->pivot->allocated_target_value);
            if ($total > (float) $subject->target_value + 0.00001) {
                $add('allocation', 'Office shares exceed the target', 'Confirmed office allocations total '.$total.' against '.$subject->target_value.'.', 'Correct the office shares using the existing assignment workflow.');
            }
            foreach ($subject->offices()->get() as $office) {
                $assigned = $subject->assignedEmployees()->where('users.office_id', $office->id)->get()->sum(fn ($u) => (float) $u->pivot->allocated_target_value);
                if ($assigned > (float) $office->pivot->allocated_target_value + 0.00001) {
                    $add('allocation', 'Personnel shares exceed their office allocation', 'Personnel quantities exceed a confirmed office share.', 'Review personnel allocations with the responsible office head.');
                }
            }
        }

        return $findings;
    }
}
