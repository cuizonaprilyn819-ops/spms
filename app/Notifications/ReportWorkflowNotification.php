<?php

namespace App\Notifications;

use Illuminate\Bus\Queueable;
use Illuminate\Notifications\Notification;

class ReportWorkflowNotification extends Notification
{
    use Queueable;

    public function __construct(private string $formType, private object $form, private string $action, private ?string $remarks = null) {}
    public function via(object $notifiable): array { return ['database']; }
    public function toArray(object $notifiable): array
    {
        $approvedIpcr = $this->formType === 'IPCR' && $this->action === 'approved' && (int) $this->form->employee_id === (int) $notifiable->id;
        return [
            'title' => $this->formType.' report '.$this->action,
            'message' => 'The '.$this->formType.' "'.$this->form->title.'" was '.$this->action.'.'.($this->remarks ? ' Remarks: '.$this->remarks : '').($approvedIpcr ? ' You can now export your approved report to PDF or Excel in My Reports.' : ''),
            'url' => $approvedIpcr ? route('reports.index', [], false) : route(strtolower($this->formType).'s.show', $this->form, false),
        ];
    }
}
