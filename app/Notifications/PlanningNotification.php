<?php

namespace App\Notifications;

use App\Models\CommitmentPlan;
use Illuminate\Notifications\Notification;

class PlanningNotification extends Notification
{
    public function __construct(public CommitmentPlan $plan, public string $message, public ?string $destination = null) {}

    public function via(object $notifiable): array
    {
        return ['database'];
    }

    public function toArray(object $notifiable): array
    {
        return ['title' => 'Commitment planning', 'message' => $this->message, 'url' => $this->destination ?? route('planning.show', $this->plan), 'plan_id' => $this->plan->id];
    }
}
