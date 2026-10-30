<?php

use Illuminate\Foundation\Inspiring;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Schedule;
use App\Models\PerformanceTarget;
use App\Notifications\DeadlineReminderNotification;
use App\Models\EvaluationCycle;
use Database\Seeders\EvaluationCycleSeeder;

Artisan::command('inspire', function () {
    $this->comment(Inspiring::quote());
})->purpose('Display an inspiring quote');

Artisan::command('spms:prepare-periods {--set-current : Make July-December 2026 the current open period}', function (): int {
    (new EvaluationCycleSeeder)->run((bool) $this->option('set-current'));

    $this->info('Evaluation periods for 2025-2028 are ready.');
    $this->table(['Period', 'Status'], EvaluationCycle::whereBetween('year', [2025, 2028])
        ->orderBy('year')->orderBy('semester')->get()
        ->map(fn (EvaluationCycle $cycle) => [$cycle->label, $cycle->isOpen() ? 'Current / Open' : 'Closed'])
        ->all());

    return self::SUCCESS;
})->purpose('Add the eight half-year periods without replacing existing records.');

Artisan::command('spms:send-deadline-reminders {--days=7}', function (): int {
    $days = (int) $this->option('days');
    $start = now()->startOfDay();
    $end = now()->addDays($days)->endOfDay();
    $sent = 0;

    PerformanceTarget::with('createdBy')
        ->whereBetween('deadline', [$start->toDateString(), $end->toDateString()])
        ->each(function (PerformanceTarget $target) use (&$sent): void {
            $recipient = $target->createdBy;

            if (! $recipient) {
                return;
            }

            $alreadySent = $recipient->notifications()
                ->where('type', DeadlineReminderNotification::class)
                ->where('data->target_id', $target->id)
                ->exists();

            if ($alreadySent) {
                return;
            }

            $recipient->notify(new DeadlineReminderNotification($target));
            $sent++;
        });

    $this->info($sent.' deadline reminder notification(s) sent.');

    return self::SUCCESS;
})->purpose('Send deadline reminder notifications for performance targets due soon.');

Schedule::command('spms:send-deadline-reminders')->dailyAt('08:00');
