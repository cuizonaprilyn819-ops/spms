<?php

namespace App\Services;

use App\Models\{Ipcr, TargetDocumentDraft};
use Illuminate\Database\Eloquent\Model;

class ReportTargetContext
{
    public function source(Model $report): array
    {
        $saved = json_decode($report->getRawOriginal('target_context') ?? 'null', true) ?? [];
        if (isset($saved['source'])) return $saved['source'];
        $target = $report instanceof Ipcr ? $report->officeTarget : null;
        $draft = $target ? TargetDocumentDraft::where('office_target_id', $target->id)->first() : null;
        $proposal = $draft?->proposal ?? ($target ? \App\Models\TargetProposal::where('proposal->approved_office_target_id', $target->id)->where('state', 'accepted')->first()?->proposal : null) ?? [];
        $allocation = $target?->assignedEmployees()->whereKey($report->employee_id)->first()?->pivot->allocated_target_value;
        return [
            'strategic_program' => $proposal['program'] ?? null,
            'program_outcome' => $proposal['program_outcome'] ?? null,
            'success_indicators' => $target?->success_indicator ?? $report->success_indicators,
            'source_excerpt' => $proposal['source_excerpt'] ?? $target?->description,
            'quantity' => $allocation ?? $target?->target_value,
            'unit_of_measure' => $target?->unit_of_measure,
            'weight' => $target?->weight,
            'deadline' => $target?->deadline?->toDateString(),
            'office' => $report->employee?->office?->name,
        ];
    }

    public function display(Model $report): array
    {
        $saved = json_decode($report->getRawOriginal('target_context') ?? 'null', true) ?? [];
        return [...$this->source($report), ...$saved, 'success_indicators' => $report->success_indicators];
    }
}
