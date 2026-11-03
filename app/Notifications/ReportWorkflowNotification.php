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
        $submitter = in_array($this->action, ['submitted', 'resubmitted'], true) ? $this->form->preparedBy : null;
        $office = $this->form->employee?->office ?? ($this->formType === 'OPCR' ? $this->form->office : $submitter?->office);
        $message = $submitter && (int) $submitter->id === (int) $notifiable->id
            ? 'You '.$this->action.' your '.$this->formType.' report "'.$this->form->title.'" to your supervisor.'
            : 'The '.$this->formType.' "'.$this->form->title.'" was '.$this->action.'.'.($submitter ? ' Submitted by '.$submitter->name.($office ? ' — '.$office->name : '').'.' : '');
        return [
            'title' => $this->formType.' report '.$this->action,
            'message' => $message.($this->remarks ? ' Remarks: '.$this->remarks : '').($approvedIpcr ? ' You can now export your approved report to PDF, Excel or Word in My Reports.' : ''),
            'report_type' => strtolower($this->formType),
            'report_id' => $this->form->id,
            'office_id' => $office?->id,
            'url' => $approvedIpcr ? route('reports.index', [], false) : route(strtolower($this->formType).'s.show', $this->form, false),
        ];
    }
}
