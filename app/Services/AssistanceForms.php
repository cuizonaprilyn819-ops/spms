<?php

namespace App\Services;

use App\Models\EvaluationCycle;
use App\Models\User;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\Gate;

class AssistanceForms
{
    public const PROTECTED = ['quantity', 'target_value', 'unit', 'unit_of_measure', 'measurement', 'whole_numbers', 'deadline', 'start_date', 'weight', 'standards', 'source_evidence', 'source_excerpt', 'coverage', 'quantity_basis', 'campus_ids', 'recipient_ids', 'office_ids', 'allocations', 'employee_ids', 'employee_allocations', 'evaluation_cycle_id', 'office_id', 'employee_id', 'opcr_id', 'dpcr_id', 'upcr_id', 'parent_id', 'parent_target_id', 'source_target_id', 'performance_target_id', 'document_draft_id', 'proposal_id', 'revision_id', 'reference', 'submission_mode', 'numerator', 'denominator', 'completion_percentage'];

    public function __construct(public AssistanceSubject $subjects) {}

    public function protectedValues(array $values, ?Model $subject = null): array
    {
        $protected = Arr::only($values, self::PROTECTED);
        if ($subject?->exists) {
            $protected['record_constraints'] = Arr::only($subject->getAttributes(), ['quantity', 'unit', 'deadline', 'standards', 'measurement', 'root_target_id', 'parent_target_id', 'source_revision', 'plan_revision', 'scope_metadata', 'source_sha256', 'current_revision_id']);
        }
        ksort($protected);

        return $protected;
    }

    /** Resolve an existing, allowlisted form action without making a request to it. */
    public function resolve(string $action, array $values, User $u, bool $edit = true): array
    {
        $path = trim(preg_replace('#^/api/#', '/', parse_url($action, PHP_URL_PATH) ?? ''), '/');
        $m = null;
        $fields = null;
        $narrative = false;
        foreach ([
            '#^planning/(\d+)/(?:scope|targets)$#' => 'plan',
            '#^planning/targets/(\d+)/update$#' => 'target',
            '#^planning/targets/(\d+)/outputs$#' => 'target-output',
            '#^planning/proposals/(\d+)/resubmit$#' => 'proposal',
            '#^office-targets/(\d+)$#' => 'office-target',
            '#^office-targets/(\d+)/employees$#' => 'office-target',
            '#^target-drafts/(\d+)/assign$#' => 'document-draft',
            '#^historical-pcrs/(\d+)/corrections$#' => 'historical',
            '#^historical-pcrs/(\d+)$#' => 'historical-notes',
            '#^accomplishments/(\d+)$#' => 'accomplishment',
        ] as $pattern => $kind) {
            if (! preg_match($pattern, $path, $match)) {
                continue;
            }
            $narrative = $kind === 'target-output';
            $historicalNotes = $kind === 'historical-notes';
            $m = $this->subjects->resolve($narrative ? 'target' : ($historicalNotes ? 'historical' : $kind), (int) $match[1]);
            $fields = $narrative ? ['details'] : ($historicalNotes ? ['snapshot.notes'] : null);
            break;
        }
        if (! $m && preg_match('#^report-workflow/(opcr|dpcr|upcr|ipcr)/(\d+)$#', $path, $match)) {
            $m = $this->subjects->resolve($match[1], (int) $match[2]);
        }
        if (! $m && preg_match('#^evidence/(mov|target-mov|planning-output)/(\d+)/statement$#', $path, $match)) {
            $m = $this->subjects->resolve($match[1], (int) $match[2]);
            $fields = ['statement'];
        }
        if (! $m && preg_match('#^(opcrs|dpcrs|upcrs|ipcrs|office-targets|accomplishments|planning/proposals)$#', $path, $match)) {
            $kind = ['opcrs' => 'opcr', 'dpcrs' => 'dpcr', 'upcrs' => 'upcr', 'ipcrs' => 'ipcr', 'office-targets' => 'office-target', 'accomplishments' => 'accomplishment', 'planning/proposals' => 'proposal'][$match[1]];
            abort_unless($u->office_id || $u->hasRole('Administrator'), 403);
            if (in_array($kind, ['opcr', 'office-target'], true)) {
                abort_unless($u->hasAnyRole(['Administrator', 'Planning Officer', 'Campus Director']) || (int) $u->office?->head_id === $u->id, 403);
            }
            if (in_array($kind, ['dpcr', 'upcr'], true)) {
                abort_unless($u->hasAnyRole(['Administrator', 'Supervisor', 'HR Head']) || (int) $u->office?->head_id === $u->id, 403);
            }
            $parentKey = ['dpcr' => 'opcr_id', 'upcr' => 'dpcr_id', 'ipcr' => 'upcr_id', 'accomplishment' => 'performance_target_id'][$kind] ?? null;
            if ($parentKey && ! empty($values[$parentKey])) {
                $parent = $this->subjects->resolve(['dpcr' => 'opcr', 'upcr' => 'dpcr', 'ipcr' => 'upcr', 'accomplishment' => 'target'][$kind], (int) $values[$parentKey]);
                abort_unless($this->subjects->canView($u, $parent), 403);
            }
            if ($kind === 'proposal') {
                abort_unless((int) ($values['office_id'] ?? 0) === (int) $u->office_id || $u->hasRole('Administrator'), 403);
            }
            if (! empty($values['evaluation_cycle_id'])) {
                $cycle = EvaluationCycle::findOrFail($values['evaluation_cycle_id']);
                abort_unless($cycle->isOpen(), 422, 'This evaluation period is closed.');
            }
            $class = AssistanceSubject::TYPES[$kind];
            $m = new $class;
            $m->forceFill(['prepared_by' => $u->id, 'created_by' => $u->id, 'office_id' => $u->office_id, 'evaluation_cycle_id' => $values['evaluation_cycle_id'] ?? null]);

            return [$m, $this->subjects->fields($m)];
        }
        abort_unless($m && $this->subjects->canView($u, $m), 403);
        if ($edit) {
            if (isset($historicalNotes) && $historicalNotes) {
                abort_unless(Gate::forUser($u)->allows('update', $m), 403);
            }
            if ($narrative) {
                abort_unless($m->plan && app(PlanningOrganization::class)->canPrepare($u, $m->plan) && $m->plan->state === 'approved' && $m->plan->report->status !== 'approved' && ! $m->needs_reconciliation, 403);
            } else {
                abort_unless($this->subjects->canEdit($u, $m), 403);
            }
        }

        return [$m, $fields ?? $this->subjects->fields($m)];
    }

    public function allowed(string $field, array $allowed): bool
    {
        return in_array($field, $allowed, true) || (in_array('snapshot.notes', $allowed, true) && preg_match('/^snapshot\.notes\.\d+$/', $field));
    }
}
