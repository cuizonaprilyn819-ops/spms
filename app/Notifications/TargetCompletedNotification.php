<?php
namespace App\Notifications;
use App\Models\OfficeTarget;
use Illuminate\Notifications\Notification;
class TargetCompletedNotification extends Notification
{
    public function __construct(private OfficeTarget $target, private string $employee) {}
    public function via(object $notifiable): array { return ['database']; }
    public function toArray(object $notifiable): array {
        return ['title' => 'Employee reported target complete',
            'message' => $this->employee.' marked "'.$this->target->title.'" complete. MOV is available for review.',
            'url' => route('office-targets.show', $this->target, false).'#target-movs'];
    }
}
