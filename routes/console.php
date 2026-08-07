<?php

use Illuminate\Foundation\Inspiring;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Schedule;
use App\Models\PerformanceTarget;
use App\Notifications\DeadlineReminderNotification;

Artisan::command('inspire', function () {
    $this->comment(Inspiring::quote());
})->purpose('Display an inspiring quote');

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
