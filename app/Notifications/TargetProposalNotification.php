<?php

namespace App\Notifications;

use Illuminate\Notifications\Notification;

class TargetProposalNotification extends Notification
{
    public function __construct(public int $proposalId, public string $message) {}

    public function via(object $notifiable): array
    {
        return ['database'];
    }

    public function toArray(object $notifiable): array
    {
        return ['title' => 'Target proposal', 'message' => $this->message, 'proposal_id' => $this->proposalId, 'url' => route('planning.index')];
    }
}
