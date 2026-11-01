<?php

namespace App\Services;

use App\Models\{EvaluationCycle, OfficeTarget, User};
use App\Notifications\PcrCreationReminder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

class PcrReminders
{
    public function __construct(private PcrCompliance $compliance) {}

    public function pending(EvaluationCycle $cycle): array
    {
        $items = [];
        $window = max(1, (int) ($cycle->pcr_reminder_days ?? 7));
        $closingSoon = $cycle->pcr_creation_closes_at && now()->betweenIncluded($cycle->pcr_creation_closes_at->copy()->subDays($window), $cycle->pcr_creation_closes_at);
        $submissionSoon = now()->gte($cycle->submissionDueAt()->copy()->subDays($window));
        $users = User::whereHas('office', fn ($office) => $office->forCampus())->with(['office', 'roles'])->get();
        $assignedOffices = OfficeTarget::where('evaluation_cycle_id', $cycle->id)->with('offices')->get()->flatMap->offices->unique('id')->keyBy('id');
        foreach ($users as $user) {
            $types = $this->compliance->requiredReports($user);
            $isHead = (int) $user->office->head_id === $user->id || (! $user->office->head_id && $user->hasAnyRole(['Supervisor', 'HR Head']));
            if ($isHead && $assignedOffices->has($user->office_id) && ! in_array('upcr', $types)) $types[] = 'upcr';
            foreach ($types as $type) {
                $missingCreation = $this->compliance->missing($type, $user, $cycle);
                $missingOfficeUpcr = $type === 'upcr' && $isHead && $assignedOffices->has($user->office_id) && $missingCreation;
                $missingSubmission = ! $missingCreation && $submissionSoon && $this->compliance->missing($type, $user, $cycle, true);
                if (! $missingOfficeUpcr && ! ($closingSoon && $missingCreation) && ! $missingSubmission) continue;
                $kind = $missingSubmission ? 'submission' : 'creation';
                $deadline = $missingSubmission ? $cycle->submissionDueAt() : $cycle->pcr_creation_closes_at;
                $message = $missingOfficeUpcr
                    ? 'Targets are assigned under '.$user->office->name.', but its UPCR for '.$cycle->label.' has not been created. Please prepare the office UPCR so individual targets can follow the reporting hierarchy.'
                    : 'Your '.strtoupper($type).' for '.$cycle->label.' has not been '.($missingSubmission ? 'submitted' : 'created').'.';
                if ($deadline) $message .= ' '.ucfirst($kind).' deadline: '.$deadline->format('F j, Y H:i T').'.';
                if ($missingOfficeUpcr && $cycle->pcr_creation_closes_at?->isPast()) $message .= ' PCR creation has closed; coordinate with the Planning Officer for an authorized completion or extension.';
                $items[] = ['user' => $user, 'type' => $type, 'kind' => $kind, 'office_id' => $user->office_id,
                    'details' => ['title' => strtoupper($type).' '.$kind.' reminder', 'message' => $message, 'evaluation_cycle_id' => $cycle->id, 'report_type' => $type,
                        'url' => $cycle->planning_enabled ? route('planning.index') : route($type.'s.index')]];
            }
        }
        return $items;
    }

    public function emailAvailable(): bool
    {
        return config('spms.pcr.email_reminders', true) && ! in_array(config('mail.default'), ['log', 'array']);
    }

    public function send(bool $dryRun = false): array
    {
        $counts = ['pending' => 0, 'database' => 0, 'mail' => 0, 'failed' => 0];
        foreach (EvaluationCycle::where('status', EvaluationCycle::STATUS_OPEN)->get() as $cycle) {
            foreach ($this->pending($cycle) as $item) {
                $counts['pending']++;
                if ($dryRun) continue;
                foreach (['database', ...($this->emailAvailable() && filter_var($item['user']->email, FILTER_VALIDATE_EMAIL) ? ['mail'] : [])] as $channel) {
                    // Recheck immediately before delivery so completed tasks stop generating reminders.
                    if (! $this->compliance->missing($item['type'], $item['user'], $cycle, $item['kind'] === 'submission')) continue;
                    if ($this->deliver($cycle, $item, $channel)) $counts[$channel]++;
                    elseif ($this->lastFailed) $counts['failed']++;
                }
            }
        }
        return $counts;
    }

    private bool $lastFailed = false;

    private function deliver(EvaluationCycle $cycle, array $item, string $channel): bool
    {
        $this->lastFailed = false;
        $key = ['evaluation_cycle_id' => $cycle->id, 'user_id' => $item['user']->id, 'report_type' => $item['type'], 'office_id' => $item['office_id'], 'reminder_date' => now()->toDateString(), 'channel' => $channel];
        $token = (string) Str::uuid();
        $claimed = DB::transaction(function () use ($key, $token) {
            DB::table('pcr_reminder_deliveries')->insertOrIgnore([...$key, 'status' => 'pending', 'created_at' => now(), 'updated_at' => now()]);
            $delivery = DB::table('pcr_reminder_deliveries')->where($key)->lockForUpdate()->first();
            if ($delivery->status === 'sent' || ($delivery->status === 'sending' && $delivery->claimed_at > now()->subMinutes(15)->toDateTimeString())) return false;
            DB::table('pcr_reminder_deliveries')->where('id', $delivery->id)->update(['status' => 'sending', 'claim_token' => $token, 'claimed_at' => now(), 'updated_at' => now()]);
            return true;
        });
        if (! $claimed) return false;
        try {
            $item['user']->notify(new PcrCreationReminder($item['details'], $channel));
            DB::table('pcr_reminder_deliveries')->where($key)->where('claim_token', $token)->update(['status' => 'sent', 'sent_at' => now(), 'error' => null, 'updated_at' => now()]);
            return true;
        } catch (\Throwable $exception) {
            // Do not persist SMTP exception text: it can contain credentials or recipient data.
            DB::table('pcr_reminder_deliveries')->where($key)->where('claim_token', $token)->update(['status' => 'failed', 'error' => 'Delivery failed; verify mail configuration and retry.', 'updated_at' => now()]);
            $this->lastFailed = true;
            return false;
        }
    }
}
