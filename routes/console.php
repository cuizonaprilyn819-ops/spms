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

Artisan::command('spms:send-pcr-reminders {--dry-run : Count pending reminders without delivering notifications}', function (): int {
    $service = app(\App\Services\PcrReminders::class);
    $counts = $service->send((bool) $this->option('dry-run'));
    $this->info('Pending tasks: '.$counts['pending'].'; in-app reminders: '.$counts['database'].'; emails: '.$counts['mail'].'; failed deliveries: '.$counts['failed'].'.');
    if (! $service->emailAvailable()) $this->warn('Real email delivery is disabled or the mailer is log/array. Configure SMTP and SPMS_PCR_EMAIL_REMINDERS for outgoing email.');
    return $counts['failed'] ? self::FAILURE : self::SUCCESS;
})->purpose('Remind responsible heads and personnel about missing PCRs and pending submissions, once per day per channel.');

Schedule::command('spms:send-pcr-reminders')->dailyAt(config('spms.pcr.reminder_time', '08:00'))->withoutOverlapping(30);
