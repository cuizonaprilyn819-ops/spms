<?php

namespace App\Services;

use App\Jobs\ProcessMov;
use App\Models\{Mov, PlanningOutput, TargetMov};

class EvidenceProcessing
{
    public function queue(Mov|TargetMov|PlanningOutput $evidence, bool $analyze = true): void
    {
        $files = app(EvidenceFiles::class)->group($evidence);
        if ($analyze && $files->contains(fn ($file) => $file->analysis?->isProcessing() && $file->analysis->updated_at->gt(now()->subMinutes(15)))) return;
        $queuedAt = now()->toIso8601String();
        if ($analyze) foreach ($files as $file) $file->analysis()->updateOrCreate([], ['status' => 'queued', 'analysis_token' => null, 'statement' => null, 'summary' => null, 'error' => null, 'findings' => ['processing' => ['queued_at' => $queuedAt, 'state' => 'queued']]]);
        try {
            $type = app(EvidenceFiles::class)->type($evidence);
            if (config('spms.mov.connection') === 'sync') {
                ProcessMov::dispatchSync($type, $evidence->id, $analyze, $queuedAt);
            } else {
                ProcessMov::dispatch($type, $evidence->id, $analyze, $queuedAt)->afterCommit();
            }
        } catch (\Throwable $error) {
            if ($analyze) foreach ($files as $file) $file->analysis()->first()?->update(['status' => 'failed', 'findings' => ['processing' => ['queued_at' => $queuedAt, 'state' => 'failed']], 'error' => 'MOV saved, but processing could not be queued. Ask the administrator to check the worker, then retry.']);
            report($error);
        }
    }
}
