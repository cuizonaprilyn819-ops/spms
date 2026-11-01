<?php

namespace App\Notifications;

use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

class PcrCreationReminder extends Notification
{
    public function __construct(public array $details, private string $channel = 'database') {}

    public function via(object $notifiable): array { return [$this->channel]; }

    public function toArray(object $notifiable): array
    {
        return ['kind' => 'pcr_creation_reminder', ...$this->details];
    }

    public function toMail(object $notifiable): MailMessage
    {
        return (new MailMessage)->subject($this->details['title'])
            ->greeting('Hello '.$notifiable->name.',')->line($this->details['message'])
            ->action('Open SPMS', $this->details['url'])
            ->line('This reminder stops after the requested creation or submission is completed. Submission timeliness is recorded separately for IPCR review.');
    }
}
