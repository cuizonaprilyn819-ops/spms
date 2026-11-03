<?php

namespace App\Notifications;

use Illuminate\Notifications\Notification;

class TargetConflictNotification extends Notification
{
    public function __construct(public int $scanId, public int $count) {}

    public function via(object $notifiable): array
    {
        return ['database'];
    }

    public function toArray(object $notifiable): array
    {
        return ['title' => 'Target conflict review', 'message' => $this->count.' finding(s) need review. AI suspicions are unconfirmed; open the review to see findings you can access.', 'url' => route('target-conflicts.show', $this->scanId), 'scan_id' => $this->scanId];
    }
}
