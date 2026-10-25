<?php

namespace App\Notifications;

use App\Models\OfficeTarget;
use Illuminate\Notifications\Notification;

class EmployeeTargetAssignedNotification extends Notification
{
    public function __construct(private OfficeTarget $target, private string $supervisor) {}
    public function via(object $notifiable): array { return ['database']; }
    public function toArray(object $notifiable): array
    {
        return ['title' => 'New target assigned to you',
            'message' => $this->supervisor.' assigned "'.$this->target->title.'" to you.',
            'target_id' => $this->target->id,
            'url' => route('office-targets.show', $this->target, false)];
    }
}
