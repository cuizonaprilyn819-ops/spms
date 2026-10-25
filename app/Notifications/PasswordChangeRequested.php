<?php

namespace App\Notifications;

use App\Models\User;
use Illuminate\Bus\Queueable;
use Illuminate\Notifications\Notification;

class PasswordChangeRequested extends Notification
{
    use Queueable;

    public function __construct(private User $requester) {}

    public function via(object $notifiable): array
    {
        return ['database'];
    }

    public function toArray(object $notifiable): array
    {
        return [
            'title' => 'Password change requested',
            'message' => $this->requester->name.' ('.$this->requester->email.') requested a password change.',
            'url' => route('admin.password-resets.edit', $this->requester, false),
        ];
    }
}
