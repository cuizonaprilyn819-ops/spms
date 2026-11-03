<?php

namespace App\Services;

use App\Models\{Ipcr, Mov, PlanningOutput, TargetMov};
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\ValidationException;

class ReportEvidence
{
    public function issues(Model $report): array
    {
        if ($report instanceof Ipcr && $report->office_target_id) {
            $files = TargetMov::with('analysis')->where('office_target_id', $report->office_target_id)->where('user_id', $report->employee_id)->get();
            return $this->check($files, 'Your assigned target');
        }
        $targets = $report->commitmentPlan ? $report->commitmentPlan->targets()->get() : $report->performanceTargets()->get();
        if ($targets->isEmpty()) return ['Add the report targets and upload MOV before submitting for review.'];
        $issues = [];
        foreach ($targets as $target) {
            $files = $report->commitmentPlan
                ? PlanningOutput::with('analysis')->where('performance_target_id', $target->id)->when($report instanceof Ipcr, fn ($q) => $q->where('submitted_by', $report->employee_id))->get()
                : Mov::with('analysis')->where('performance_target_id', $target->id)->when($report instanceof Ipcr, fn ($q) => $q->where('uploaded_by', $report->employee_id))->get();
            $issues = [...$issues, ...$this->check($files, $target->target ?? $target->title ?? 'Target #'.$target->id)];
        }
        return $issues;
    }

    private function check($files, string $label): array
    {
        $files = $files->filter(function ($file) {
            $disk = $file instanceof Mov ? 'public' : 'local';
            $path = $file instanceof Mov ? $file->file_path : ($file instanceof PlanningOutput ? ($file->evidence['path'] ?? null) : $file->path);
            return $path && Storage::disk($disk)->exists($path) && Storage::disk($disk)->size($path) > 0;
        });
        if ($files->isEmpty()) return [$label.': upload MOV before submitting for review.'];
        if ($files->contains(function ($file) use ($files) {
            $analysis = $file->analysis;
            if ($analysis?->status !== 'completed' || ($analysis->findings['supported'] ?? false) !== true || ($analysis->findings['satisfies_target'] ?? false) !== true) return false;
            $context = $file->submission_context ?? null;
            if (! ($context['batch_id'] ?? null)) return true;
            $ids = $analysis->findings['batch_evidence_ids'] ?? [];
            if (! $ids || ($analysis->findings['batch_id'] ?? null) !== $context['batch_id']) return false;
            foreach ($ids as $id) {
                $member = $files->firstWhere('id', $id);
                if (! $member || ($member->submission_context['batch_id'] ?? null) !== $context['batch_id']) return false;
            }
            try {
                return app(MovQuestions::class)->snapshot($file instanceof Mov ? $file->uploadedBy : $file->employee, $context['target_snapshot']['target_ref']) === $context['target_snapshot'];
            } catch (\Throwable) {
                return false;
            }
        })) return [];
        return [$label.': MOV must complete AI analysis and satisfy the target. Review the findings, upload sufficient evidence, or retry analysis.'];
    }

    public function ensureReady(Model $report): void
    {
        if ($issues = $this->issues($report)) throw ValidationException::withMessages(['mov' => $issues]);
    }
}
