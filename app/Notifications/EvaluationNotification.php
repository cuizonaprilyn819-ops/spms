<?php

namespace App\Notifications;

use App\Models\Evaluation;
use Illuminate\Bus\Queueable;
use Illuminate\Notifications\Notification;

class EvaluationNotification extends Notification
{
    use Queueable;

    public function __construct(private readonly Evaluation $evaluation)
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
            'kind' => 'evaluation_notification',
            'title' => 'Evaluation Posted',
            'message' => 'You received an average rating of '.number_format((float) $this->evaluation->rating, 2).' ('.$this->evaluation->adjectival_rating.') for "'.$this->evaluation->performanceTarget->target.'".',
            'evaluation_id' => $this->evaluation->id,
            'url' => route('evaluations.show', $this->evaluation),
        ];
    }
}
