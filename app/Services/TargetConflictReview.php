<?php

namespace App\Services;

use App\Jobs\ScanTargetConflicts;
use App\Models\CommitmentPlan;
use App\Models\OfficeTarget;
use App\Models\TargetConflict;
use App\Models\TargetConflictDisposition;
use App\Models\TargetConflictScan;
use App\Models\User;
use App\Models\WorkflowAudit;
use App\Notifications\TargetConflictNotification;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;
use Symfony\Component\HttpKernel\Exception\HttpExceptionInterface;

class TargetConflictReview
{
    public function __construct(public AssistanceSubject $subjects, public TargetConflictRules $rules) {}

    public function context(Model $subject, User $actor): array
    {
        $subject = $this->subjects->canonical($subject);
        abort_unless($this->subjects->canView($actor, $subject), 403);
        $cycle = $this->subjects->cycleId($subject);
        $records = collect([$subject]);
        // Historical notes are separate from operational commitments and comparisons.
        if ($cycle && $this->subjects->kind($subject) !== 'historical') {
            foreach (CommitmentPlan::where('evaluation_cycle_id', $cycle)->orderBy('id')->get() as $p) {
                if ($this->subjects->canView($actor, $p)) {
                    $records->push($p);
                }
            }
            foreach (OfficeTarget::where('evaluation_cycle_id', $cycle)->whereNull('performance_target_id')->orderBy('id')->get() as $t) {
                if ($this->subjects->canView($actor, $t)) {
                    $records->push($t);
                }
            }
            foreach (PcrCompliance::REPORTS as $type => $class) {
                foreach ($class::whereDoesntHave('commitmentPlan')->orderBy('id')->get() as $report) {
                    if ($this->subjects->canView($actor, $report) && $this->subjects->cycleId($report) === $cycle) {
                        $records->push($report);
                    }
                }
            }
        }
        $records = $records->unique(fn ($m) => $this->subjects->kind($m).':'.$m->id)->sortBy(fn ($m) => $this->subjects->kind($m).':'.str_pad((string) $m->id, 20, '0', STR_PAD_LEFT))->values();
        $coverage = $records->map(fn ($m) => ['kind' => $this->subjects->kind($m), 'id' => $m->id, 'fingerprint' => $this->subjects->fingerprint($m)])->all();
        $snapshot = $records->flatMap(function ($m) {
            return array_map(fn ($line) => $line + ['subject_kind' => $this->subjects->kind($m), 'subject_id' => $m->id], $this->subjects->lines($m));
        })->values()->all();

        return ['fingerprint' => hash('sha256', json_encode(['engine' => 1, 'coverage' => $coverage], JSON_THROW_ON_ERROR)), 'coverage' => $coverage, 'snapshot' => $snapshot];
    }

    public function scan(Model $model, User $actor, bool $ai = false, bool $publish = false): TargetConflictScan
    {
        $subject = $this->subjects->canonical($model);
        $context = $this->context($subject, $actor);
        $kind = $this->subjects->kind($subject);
        $existing = TargetConflictScan::where('subject_kind', $kind)->where('subject_id', $subject->id)->where('fingerprint', $context['fingerprint'])->where(fn ($q) => $q->where('user_id', $actor->id)->orWhere('published', true))->latest('id')->first();
        if ($existing?->status === 'pending' && $existing->updated_at->lt(now()->subMinutes(10))) {
            $existing->update(['status' => 'failed', 'failure' => 'The background check exceeded its processing window. Retry or record a manual conflict review.', 'finished_at' => now()]);
        }
        if ($existing && (! $ai || in_array($existing->status, ['pending', 'completed'], true))) {
            if ($publish && ! $existing->published) {
                $existing->update(['published' => true]);
            }
            if ($publish) {
                $this->notify($existing);
            }

            return $existing;
        }
        $scan = DB::transaction(function () use ($subject, $actor, $context, $kind, $ai, $publish) {
            $scan = TargetConflictScan::create($context + ['subject_kind' => $kind, 'subject_id' => $subject->id, 'user_id' => $actor->id, 'evaluation_cycle_id' => $this->subjects->cycleId($subject), 'office_id' => $this->subjects->officeId($subject), 'status' => $ai ? 'pending' : 'rules-only', 'published' => $publish]);
            foreach ($this->rules->inspect($subject) as $finding) {
                $this->finding($scan, 'rule', $finding);
            }

            return $scan;
        });
        if ($publish) {
            $this->notify($scan);
        }
        if ($ai) {
            if (! config('spms.ai.enabled')) {
                $scan->update(['status' => 'failed', 'failure' => 'AI review is unavailable. An independent reviewer may record a manual conflict review.', 'finished_at' => now()]);
            } else {
                try {
                    ScanTargetConflicts::dispatch($scan->id)->afterCommit();
                } catch (\Throwable $e) {
                    report($e);
                    $scan->update(['status' => 'failed', 'failure' => 'The AI check could not be queued. Retry or record a manual conflict review.', 'finished_at' => now()]);
                }
            }
        }

        return $scan->fresh();
    }

    public function finding(TargetConflictScan $scan, string $source, array $finding): TargetConflict
    {
        $fingerprint = hash('sha256', json_encode([$scan->subject_kind, $scan->subject_id, $scan->fingerprint, $source, $finding['code'], $finding['references'] ?? []], JSON_THROW_ON_ERROR));

        return $scan->findings()->firstOrCreate(['fingerprint' => $fingerprint], $finding + ['source' => $source]);
    }

    public function current(TargetConflictScan $scan): bool
    {
        try {
            $actor = User::findOrFail($scan->user_id);
            $subject = $this->subjects->resolve($scan->subject_kind, $scan->subject_id);

            return hash_equals($scan->fingerprint, $this->context($subject, $actor)['fingerprint']);
        } catch (\Throwable) {
            return false;
        }
    }

    public function canView(User $u, TargetConflictScan $scan): bool
    {
        try {
            $m = $this->subjects->resolve($scan->subject_kind, $scan->subject_id);

            return $this->subjects->canView($u, $m) && ($scan->published || $scan->user_id === $u->id);
        } catch (\Throwable) {
            return false;
        }
    }

    public function notify(TargetConflictScan $scan): void
    {
        if (! $scan->published || ! $this->current($scan)) {
            return;
        }
        $subject = $this->subjects->resolve($scan->subject_kind, $scan->subject_id);
        $recipients = $this->subjects->recipients($subject);
        foreach ($scan->findings as $finding) {
            foreach ($finding->references as $ref) {
                [$kind, $id] = array_pad(explode(':', $ref, 2), 2, null);
                if (! isset(AssistanceSubject::TYPES[$kind]) || ! ctype_digit((string) $id)) {
                    continue;
                }
                try {
                    $related = $this->subjects->resolve($kind, (int) $id);
                    $recipients = $recipients->merge($this->subjects->recipients($related));
                } catch (\Throwable) { /* Deleted or inaccessible references do not broaden delivery. */
                }
            }
        }
        foreach ($recipients->unique('id') as $person) {
            if (! $this->canView($person, $scan)) {
                continue;
            }
            $new = 0;
            DB::transaction(function () use ($scan, $person, &$new) {
                foreach ($scan->findings as $finding) {
                    $new += DB::table('target_conflict_deliveries')->insertOrIgnore(['fingerprint' => $finding->fingerprint, 'user_id' => $person->id, 'created_at' => now(), 'updated_at' => now()]);
                }
                if ($new) {
                    $person->notify(new TargetConflictNotification($scan->id, $new));
                }
            });
        }
    }

    /** Give each affected record its own access-controlled case, without copying work or granting access. */
    public function shareAffected(TargetConflictScan $scan): void
    {
        if (! $scan->published || ! $this->current($scan)) {
            return;
        }
        $actor = User::findOrFail($scan->user_id);
        foreach ($scan->findings()->where('source', 'ai')->get() as $finding) {
            $seen = [];
            foreach ($finding->references as $ref) {
                [$kind, $id] = array_pad(explode(':', $ref, 2), 2, null);
                try {
                    $related = $this->subjects->canonical($this->subjects->resolve($kind, (int) $id));
                    $relatedKind = $this->subjects->kind($related);
                    $key = $relatedKind.':'.$related->id;
                    if (isset($seen[$key]) || ($relatedKind === $scan->subject_kind && $related->id === $scan->subject_id)) {
                        continue;
                    }
                    $seen[$key] = true;
                    if (! $this->subjects->canView($actor, $related)) {
                        continue;
                    }
                    $context = $this->context($related, $actor);
                    $mirror = TargetConflictScan::firstOrCreate(['subject_kind' => $relatedKind, 'subject_id' => $related->id, 'user_id' => $actor->id, 'fingerprint' => $context['fingerprint'], 'published' => true], $context + ['evaluation_cycle_id' => $this->subjects->cycleId($related), 'office_id' => $this->subjects->officeId($related), 'status' => $scan->status, 'failure' => $scan->failure, 'finished_at' => now()]);
                    foreach ($this->rules->inspect($related) as $rule) {
                        $this->finding($mirror, 'rule', $rule);
                    }
                    $this->finding($mirror, 'ai', $finding->only(['code', 'title', 'explanation', 'recommendation', 'references', 'blocking']));
                    $this->notify($mirror->fresh());
                } catch (ModelNotFoundException|HttpExceptionInterface) {
                    continue;
                }
            }
        }
    }

    public function disposition(TargetConflictScan $scan, User $actor, string $decision, string $reason, ?TargetConflict $finding = null): void
    {
        $subject = $this->subjects->resolve($scan->subject_kind, $scan->subject_id);
        abort_unless($this->canView($actor, $scan) && $this->subjects->canReview($actor, $subject), 403);
        DB::transaction(function () use ($scan, $actor, $decision, $reason, $finding, $subject) {
            TargetConflictScan::whereKey($scan->id)->lockForUpdate()->firstOrFail();
            abort_unless($this->current($scan), 422, 'This result is outdated. Run a new conflict check.');
            if ($decision === 'manual-review') {
                abort_unless(in_array($scan->status, ['failed', 'rules-only'], true), 422, 'Wait for the AI check or use its findings.');
            } else {
                abort_unless($finding && $finding->scan_id === $scan->id, 422);
                abort_if($finding->blocking && $decision !== 'correction-required', 422, 'A verified rule violation must be corrected; it cannot be dismissed.');
                $finding->update(['status' => $decision]);
            }
            $d = TargetConflictDisposition::create(['scan_id' => $scan->id, 'conflict_id' => $finding?->id, 'user_id' => $actor->id, 'decision' => $decision, 'reason' => $reason, 'fingerprint' => $scan->fingerprint]);
            WorkflowAudit::record($subject, 'Target conflict reviewer disposition', null, ['disposition_id' => $d->id, 'decision' => $decision, 'reason' => $reason]);
        });
    }

    public function assertApproval(Model $model, User $reviewer): void
    {
        if (! config('spms.target_assistance.enabled')) {
            return;
        }
        $subject = $this->subjects->canonical($model);
        // All authorization checks still run at their original approval entry points.
        abort_unless($this->subjects->canReview($reviewer, $subject), 403);
        if (collect($this->rules->inspect($subject))->contains('blocking', true)) {
            $this->fail('Correct the verified target rule violations before approval.');
        }
        $scan = TargetConflictScan::where('subject_kind', $this->subjects->kind($subject))->where('subject_id', $subject->id)->where('published', true)->latest('id')->get()->first(fn ($s) => $this->current($s));
        if (! $scan) {
            $this->fail('Run a current conflict check before approval.');
        }
        if (in_array($scan->status, ['pending', 'outdated'], true)) {
            $this->fail('Wait for the current AI conflict check or retry an outdated result.');
        }
        $manual = $scan->dispositions()->where('decision', 'manual-review')->where('user_id', $reviewer->id)->exists();
        if ($scan->status !== 'completed' && ! $manual) {
            $this->fail('AI review is unavailable. Record a manual conflict review before approval.');
        }
        foreach ($scan->findings as $f) {
            if ($f->blocking) {
                $this->fail('Correct the verified target rule violations before approval.');
            }
            $last = $f->dispositions()->where('user_id', $reviewer->id)->latest('id')->first();
            if (! $last || ! in_array($last->decision, ['addressed', 'dismissed'], true)) {
                $this->fail('Record a reviewer response for every current warning before approval.');
            }
        }
    }

    private function fail(string $message): never
    {
        throw ValidationException::withMessages(['conflicts' => $message]);
    }
}
