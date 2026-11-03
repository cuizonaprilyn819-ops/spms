<?php

namespace App\Jobs;

use App\Models\TargetConflictScan;
use App\Services\AiAssistant;
use App\Services\TargetConflictReview;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\Rule;

class ScanTargetConflicts implements ShouldQueue
{
    use Queueable;

    public int $tries = 2;

    public int $timeout = 240;

    public function __construct(public int $scanId)
    {
        $this->onConnection('target-assistance')->onQueue('target-assistance');
    }

    public function handle(TargetConflictReview $reviews, AiAssistant $ai): void
    {
        $scan = TargetConflictScan::findOrFail($this->scanId);
        if ($scan->status !== 'pending') {
            return;
        }
        if (! $reviews->current($scan)) {
            $scan->update(['status' => 'outdated', 'finished_at' => now()]);

            return;
        }
        $own = array_values(array_filter($scan->snapshot, fn ($l) => $l['subject_kind'] === $scan->subject_kind && $l['subject_id'] === $scan->subject_id));
        $findings = [];
        try {
            // Every visible candidate is covered in bounded batches; no hidden first-N cutoff.
            foreach (array_chunk($scan->snapshot, 25) ?: [[]] as $chunk) {
                $allowed = array_values(array_unique(array_column([...$own, ...$chunk], 'key')));
                if (! $allowed) {
                    continue;
                }
                $raw = $ai->draft('conflicts', ['subject_lines' => $own, 'candidate_lines' => $chunk]);
                $data = json_decode($raw, true, 32, JSON_THROW_ON_ERROR);
                Validator::make($data, ['findings' => ['required', 'array', 'max:25'], 'findings.*.code' => ['required', Rule::in(['possible_duplicate', 'wording_inconsistency', 'accountability_overlap', 'duty_mismatch', 'needs_information'])], 'findings.*.title' => ['required', 'string', 'max:255'], 'findings.*.explanation' => ['required', 'string', 'max:4000'], 'findings.*.recommendation' => ['required', 'string', 'max:2000'], 'findings.*.references' => ['required', 'array', 'min:1', 'max:10'], 'findings.*.references.*' => ['required', 'string', Rule::in($allowed)]])->validate();
                foreach ($data['findings'] as $finding) {
                    if (! array_intersect($finding['references'], array_column($own, 'key'))) {
                        throw new \RuntimeException('AI referenced an unrelated subject.');
                    }
                    $referenced = array_values(array_filter([...$own, ...$chunk], fn ($line) => in_array($line['key'], $finding['references'], true)));
                    // Identical wording along a known cascade is expected, never a duplicate finding.
                    if ($finding['code'] === 'possible_duplicate' && count(array_unique(array_filter(array_column($referenced, 'root')))) === 1 && count(array_filter(array_column($referenced, 'root'))) === count($referenced)) {
                        continue;
                    }
                    $findings[] = $finding + ['blocking' => false];
                }
            }
            if (! $reviews->current($scan)) {
                $scan->update(['status' => 'outdated', 'finished_at' => now()]);

                return;
            }
            foreach ($findings as $finding) {
                $reviews->finding($scan, 'ai', $finding);
            }
            $scan->update(['status' => 'completed', 'failure' => null, 'finished_at' => now()]);
            $reviews->notify($scan->fresh());
            $reviews->shareAffected($scan->fresh());
        } catch (\Throwable $e) {
            $scan->update(['status' => 'failed', 'failure' => 'AI review could not finish. No clean result is claimed. Retry or record a manual conflict review.', 'finished_at' => now()]);
            report($e);
        }
    }

    public function failed(?\Throwable $exception): void
    {
        TargetConflictScan::whereKey($this->scanId)->where('status', 'pending')->update(['status' => 'failed', 'failure' => 'The background check did not finish. Retry or record a manual conflict review.', 'finished_at' => now()]);
    }
}
