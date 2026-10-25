<?php

namespace App\Jobs;

use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Notifications\DatabaseNotification;
use Illuminate\Support\Facades\Mail;

class EmailStoredNotification implements ShouldQueue
{
    use Queueable;

    public int $tries = 3;
    public function backoff(): array { return [60, 300, 900]; }
    public function __construct(public string $notificationId) {}

    public function handle(): void
    {
        if (!config('spms.email_notifications')) return;
        $notice = DatabaseNotification::find($this->notificationId);
        if (!$notice || $notice->read_at || !$notice->notifiable?->email) return;
        $title = $notice->data['title'] ?? 'SPMS notification';
        $message = $notice->data['message'] ?? 'You have a new SPMS notification.';
        Mail::raw($message."\n\nOpen your notifications: ".rtrim(config('app.url'), '/').'/notifications', function ($mail) use ($notice, $title) {
            $mail->to($notice->notifiable->email)->subject($title);
        });
    }
}
