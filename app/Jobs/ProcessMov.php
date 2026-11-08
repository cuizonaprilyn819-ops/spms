<?php

namespace App\Jobs;

use App\Models\{Mov, PlanningOutput, TargetMov};
use App\Services\{EvidenceAi, EvidenceFiles, EvidenceOcr};
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Contracts\Queue\ShouldBeUnique;
use Illuminate\Foundation\Queue\Queueable;

class ProcessMov implements ShouldQueue, ShouldBeUnique
{
    use Queueable;

    public int $tries = 1;
    public int $timeout = 600;
    public int $uniqueFor = 900;

    public function __construct(public string $type, public int $id, public bool $analyze = true, public ?string $queuedAt = null)
    {
        $this->onConnection(config('spms.mov.connection', 'mov-processing'))->onQueue('mov-processing');
    }

    private function evidence(): Mov|TargetMov|PlanningOutput|null
    {
        return match ($this->type) { 'mov' => Mov::find($this->id), 'target-mov' => TargetMov::find($this->id), 'planning-output' => PlanningOutput::find($this->id), default => null };
    }

    public function uniqueId(): string
    {
        $file = $this->evidence();
        $batch = $file?->submission_context['batch_id'] ?? null;
        return $this->type.':'.($batch ?: $this->id).':'.($this->analyze ? 'analysis' : 'preview');
    }

    public function handle(EvidenceFiles $media, EvidenceAi $ai, EvidenceOcr $ocr): void
    {
        $evidence = $this->evidence();
        if (! $evidence) return;
        $start = hrtime(true);
        $files = $media->group($evidence);
        if ($this->analyze) foreach ($files as $file) {
            $analysis = $file->analysis()->firstOrCreate([]);
            $analysis->update(['findings' => [...($analysis->findings ?? []), 'processing' => ['queued_at' => $this->queuedAt, 'state' => 'running']]]);
        }
        foreach ($files as $file) {
            try { $media->preview($file); } catch (\Throwable $error) { report($error); }
        }
        $previewMs = (int) ((hrtime(true) - $start) / 1e6);
        if (! $this->analyze) return;
        $aiStart = hrtime(true);
        $ai->analyze($evidence);
        $aiMs = (int) ((hrtime(true) - $aiStart) / 1e6);
        $ocrStart = hrtime(true);
        $texts = [];
        if ($ocr->needed($evidence->fresh()->analysis?->findings ?? [])) {
            foreach ($files as $file) {
                $text = $ocr->extract($file);
                if ($text) $texts[$file->id] = mb_substr($text, 0, max(1, (int) (24000 / $files->count())));
            }
        }
        $ocrMs = (int) ((hrtime(true) - $ocrStart) / 1e6);
        if ($texts) {
            $aiStart = hrtime(true);
            $ai->analyze($evidence, $texts);
            $aiMs += (int) ((hrtime(true) - $aiStart) / 1e6);
        }
        foreach ($files as $file) {
            $analysis = $file->fresh()->analysis;
            if (! $analysis) continue;
            $analysis->update(['findings' => [...($analysis->findings ?? []), 'processing' => ['queued_at' => $this->queuedAt, 'state' => 'finished', 'finished_at' => now()->toIso8601String(), 'total_ms' => (int) ((hrtime(true) - $start) / 1e6), 'preview_ms' => $previewMs, 'ocr_ms' => $ocrMs, 'ai_ms' => $aiMs, 'ocr_used' => (bool) $texts]]]);
        }
    }

    public function failed(?\Throwable $error): void
    {
        $file = $this->evidence();
        if (! $file || ! $this->analyze) return;
        foreach (app(EvidenceFiles::class)->group($file) as $member) {
            $analysis = $member->analysis;
            if ($analysis && (in_array($analysis->status, ['queued', 'analyzing'], true) || ($analysis->findings['processing']['state'] ?? null) === 'running')) {
                $analysis->update(['status' => 'failed', 'analysis_token' => null, 'findings' => [...($analysis->findings ?? []), 'processing' => ['state' => 'failed']], 'error' => 'Processing stopped or exceeded its time limit. Your original MOV is saved. Retry analysis.']);
            }
        }
    }
}
