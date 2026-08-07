<?php

namespace App\Notifications;

use Illuminate\Bus\Queueable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Notifications\Notification;

class ApprovalNotification extends Notification
{
    use Queueable;

    public function __construct(
        private readonly string $formType,
        private readonly Model $form,
    ) {
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
            'kind' => 'approval_notification',
            'title' => $this->formType.' Approved',
            'message' => 'Your '.$this->formType.' "'.$this->form->title.'" has been approved.',
            'form_type' => $this->formType,
            'form_id' => $this->form->getKey(),
            'url' => route(strtolower($this->formType).'s.show', $this->form),
        ];
    }
}
