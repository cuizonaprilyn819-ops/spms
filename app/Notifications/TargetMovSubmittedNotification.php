<?php
namespace App\Notifications;

use App\Models\TargetMov;
use Illuminate\Notifications\Notification;
class TargetMovSubmittedNotification extends Notification
{
    public function __construct(private TargetMov $mov, private array $movIds = []) {}
    public function via(object $notifiable): array { return ['database']; }
    public function toArray(object $notifiable): array {
        $self = (int) $notifiable->id === (int) $this->mov->user_id;
        return ['title' => $self ? 'Your evidence was submitted' : 'MOV submitted for review',
            'target_id' => $this->mov->office_target_id,
            'mov_id' => $this->mov->id,
            'mov_ids' => $this->movIds ?: [$this->mov->id],
            'submitted_by' => $this->mov->user_id,
            'submitted_by_name' => $this->mov->employee->name,
            'office_id' => $this->mov->office_id,
            'message' => $self
                ? 'You submitted your evidence for "'.$this->mov->target->title.'" to your supervisor.'
                : $this->mov->employee->name.' submitted evidence for "'.$this->mov->target->title.'".',
            'url' => route('office-targets.show', $this->mov->office_target_id, false).'#target-movs'];
    }
}
