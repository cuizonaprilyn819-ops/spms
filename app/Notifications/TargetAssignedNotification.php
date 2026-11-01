<?php

namespace App\Notifications;

use App\Models\OfficeTarget;
use Illuminate\Notifications\Notification;

class TargetAssignedNotification extends Notification
{
    public function __construct(private OfficeTarget $target, private bool $updated = false) {}

    public function via(object $notifiable): array
    {
        return ['database'];
    }

    public function toArray(object $notifiable): array
    {
        $office = $this->target->offices()->whereKey($notifiable->office_id)->first();
        $briefing = json_decode($office?->pivot->ai_briefing ?? 'null', true);
        return [
            'title' => $this->updated ? 'Office target updated' : 'New target assigned to your office',
            'message' => ($briefing ? 'AI office briefing: '.$briefing['wording']."\n" : '')
                .($this->target->createdBy?->name ?? 'A target manager').($this->updated ? ' updated "' : ' assigned "').$this->target->title.'" to your office.'
                .($office ? ' Allocation: '.$office->pivot->allocated_target_value.' '.$this->target->unit_of_measure.'. Deadline: '.$this->target->deadline->format('Y-m-d').'.' : ''),
            'recommendation' => $briefing['recommendation'] ?? null,
            'recommendation_reason' => $briefing['reason'] ?? null,
            'office_id' => $notifiable->office_id,
            'target_id' => $this->target->id,
            'url' => route('office-targets.show', $this->target, false),
        ];
    }
}
