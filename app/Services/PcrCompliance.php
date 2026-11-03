<?php

namespace App\Services;

use App\Models\Dpcr;
use App\Models\EvaluationCycle;
use App\Models\Ipcr;
use App\Models\Office;
use App\Models\OfficeTarget;
use App\Models\Opcr;
use App\Models\PcrSubmission;
use App\Models\Upcr;
use App\Models\User;
use Illuminate\Database\Eloquent\Model;

class PcrCompliance
{
    public const REPORTS = ['opcr' => Opcr::class, 'dpcr' => Dpcr::class, 'upcr' => Upcr::class, 'ipcr' => Ipcr::class];

    public function cycle(Model $report): ?EvaluationCycle
    {
        if ($report->commitmentPlan) {
            return $report->commitmentPlan->cycle;
        }

        return match (true) {
            $report instanceof Opcr => $report->evaluationCycle,
            $report instanceof Dpcr => $report->opcr?->evaluationCycle,
            $report instanceof Upcr => $report->dpcr?->opcr?->evaluationCycle,
            $report instanceof Ipcr => $report->officeTarget?->evaluationCycle ?? $report->upcr?->dpcr?->opcr?->evaluationCycle,
            default => null,
        };
    }

    public function requiredReports(User $user): array
    {
        $types = ['ipcr'];
        if ($user->office && ($organizationalType = $user->organizationalReportType())) $types[] = $organizationalType;
        return $types;
    }

    public function reports(string $type, User $user, EvaluationCycle $cycle)
    {
        $class = self::REPORTS[$type];
        $query = $class::query()->where(function ($q) use ($type, $user) {
            if ($type === 'opcr') {
                $q->where('office_id', $user->office_id);
            } elseif ($type === 'ipcr') {
                $q->where('employee_id', $user->id);
            } else {
                $q->whereHas('employee', fn ($person) => $person->where('office_id', $user->office_id));
            }
        });

        return $query->where(function ($q) use ($type, $cycle) {
            $q->whereHas('commitmentPlan', fn ($plan) => $plan->where('evaluation_cycle_id', $cycle->id));
            $q->orWhere(function ($legacy) use ($type, $cycle) {
                $legacy->whereDoesntHave('commitmentPlan')->where(function ($record) use ($type, $cycle) {
                    match ($type) {
                        'opcr' => $record->where('evaluation_cycle_id', $cycle->id),
                        'dpcr' => $record->whereHas('opcr', fn ($parent) => $parent->where('evaluation_cycle_id', $cycle->id)),
                        'upcr' => $record->whereHas('dpcr.opcr', fn ($parent) => $parent->where('evaluation_cycle_id', $cycle->id)),
                        'ipcr' => $record->whereHas('officeTarget', fn ($target) => $target->where('evaluation_cycle_id', $cycle->id))
                            ->orWhere(fn ($manual) => $manual->whereNull('office_target_id')->whereHas('upcr.dpcr.opcr', fn ($parent) => $parent->where('evaluation_cycle_id', $cycle->id))),
                    };
                });
            });
        });
    }

    public function missing(string $type, User $user, EvaluationCycle $cycle, bool $submission = false): bool
    {
        $query = $this->reports($type, $user, $cycle);
        if ($submission) {
            $query->where(fn ($q) => $q->whereHas('submissionRecord')->orWhereNotNull('submitted_at')->orWhereIn('status', ['submitted', 'resubmitted', 'approved'])->orWhereHas('commitmentPlan', fn ($plan) => $plan->whereIn('state', ['submitted', 'endorsed', 'approved'])));
        }
        if (! $query->exists()) {
            return true;
        }
        if ($type === 'ipcr') {
            $reportedTargets = (clone $query)->whereNotNull('office_target_id')->pluck('office_target_id');

            return OfficeTarget::where('evaluation_cycle_id', $cycle->id)->whereNull('performance_target_id')
                ->whereHas('assignedEmployees', fn ($person) => $person->whereKey($user->id))->whereNotIn('id', $reportedTargets)->exists();
        }

        return false;
    }

    public function recordSubmission(Model $report, string $phase = 'performance'): void
    {
        $cycle = $this->cycle($report);
        if (! $cycle) {
            return;
        }
        PcrSubmission::firstOrCreate(['report_type' => $report->getMorphClass(), 'report_id' => $report->id], [
            'evaluation_cycle_id' => $cycle->id,
            'employee_id' => $report->employee_id ?? $report->prepared_by,
            'phase' => $phase,
            'due_at' => $cycle->submissionDueAt(), 'first_submitted_at' => now(),
        ]);
    }

    public function summary(User $user, EvaluationCycle $cycle): array
    {
        $rows = [];
        foreach ($this->requiredReports($user) as $type) {
            $reports = $this->reports($type, $user, $cycle)->with('submissionRecord')->get();
            $records = $reports->pluck('submissionRecord')->filter();
            $missing = $this->missing($type, $user, $cycle, true);
            $legacy = $reports->contains(fn ($report) => ! $report->submissionRecord && ($report->submitted_at || in_array($report->status, ['submitted', 'resubmitted', 'approved']) || in_array($report->commitmentPlan?->state, ['submitted', 'endorsed', 'approved'])));
            $rows[] = ['type' => strtoupper($type), 'status' => $missing ? (now()->gt($cycle->submissionDueAt()) ? 'Overdue / not submitted' : 'Not yet submitted') : ($records->contains(fn ($record) => $record->isLate()) ? 'Late' : ($legacy ? 'Submitted — legacy timing not verified' : 'On time')),
                'due_at' => $cycle->submissionDueAt(), 'submitted_at' => $records->sortByDesc('first_submitted_at')->first()?->first_submitted_at];
        }

        return $rows;
    }
}
