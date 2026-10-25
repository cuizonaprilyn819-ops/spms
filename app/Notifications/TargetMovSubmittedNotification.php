<?php
namespace App\Notifications;

use App\Models\TargetMov;
use Illuminate\Notifications\Notification;
class TargetMovSubmittedNotification extends Notification
{
    public function __construct(private TargetMov $mov) {}
    public function via(object $notifiable): array { return ['database']; }
    public function toArray(object $notifiable): array {
        return ['title' => 'MOV submitted for review',
            'target_id' => $this->mov->office_target_id,
            'mov_id' => $this->mov->id,
            'message' => $this->mov->employee->name.' submitted evidence for "'.$this->mov->target->title.'".',
            'url' => route('office-targets.show', $this->mov->office_target_id, false).'#target-movs'];
    }
}
