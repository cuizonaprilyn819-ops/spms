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
        $recipientOfficeIds = \App\Models\Office::where('head_id', $notifiable->id)->pluck('id')->push($notifiable->office_id)->filter()->all();
        $childOffices = $this->target->offices()->get()->filter(fn ($assigned) => (bool) array_intersect($recipientOfficeIds, app(\App\Services\PlanningOrganization::class)->ancestors($assigned)));
        $destination = $office ? 'your office' : $childOffices->pluck('name')->join(', ');
        $briefing = json_decode($office?->pivot->ai_briefing ?? 'null', true);
        return [
            'title' => $this->updated ? 'Office target updated' : ($office ? 'New target assigned to your office' : 'New target assigned to an office under your supervision'),
            'message' => ($briefing ? 'AI office briefing: '.$briefing['wording']."\n" : '')
                .($this->target->createdBy?->name ?? 'A target manager').($this->updated ? ' updated "' : ' assigned "').$this->target->title.'" to '.($destination ?: 'an office under your supervision').'.'
                .($office ? ' Allocation: '.$office->pivot->allocated_target_value.' '.$this->target->unit_of_measure.'. Deadline: '.$this->target->deadline->format('Y-m-d').'.' : ''),
            'recommendation' => $briefing['recommendation'] ?? null,
            'recommendation_reason' => $briefing['reason'] ?? null,
            'office_id' => $notifiable->office_id,
            'assigned_office_ids' => $childOffices->modelKeys(),
            'target_id' => $this->target->id,
            'url' => route('office-targets.show', $this->target, false),
        ];
    }
}
