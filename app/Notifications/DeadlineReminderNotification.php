<?php

namespace App\Notifications;

use App\Models\PerformanceTarget;
use Illuminate\Bus\Queueable;
use Illuminate\Notifications\Notification;

class DeadlineReminderNotification extends Notification
{
    use Queueable;

    public function __construct(private readonly PerformanceTarget $target)
    {
    }

    /**
     * @return array<int, string>
     */
    public function via(object $notifiable): array
    {
        return ['database'];
    }

    /**
     * @return array<string, mixed>
     */
    public function toArray(object $notifiable): array
    {
        return [
            'kind' => 'deadline_reminder',
            'title' => 'Deadline Reminder',
            'message' => 'The target "'.$this->target->target.'" is due on '.$this->target->deadline->format('F d, Y').'.',
            'target_id' => $this->target->id,
            'url' => route('performance-targets.index'),
        ];
    }
}
