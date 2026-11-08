<?php

namespace App\Services;

use App\Models\{Ipcr, TargetDocumentDraft, TargetMov, User};
use Illuminate\Support\Collection;

class IpcrPdfFormat
{
    public function data(Ipcr $report, Collection $targets, array $rows): array
    {
        $employee = $report->employee;
        $office = $employee?->office;
        $reviewer = $office?->planningReviewer ?? User::role('Planning Officer')->first();
        $approver = $report->approvedBy ?? $office?->parentOffice?->head;
        $sections = ['Strategic Objectives' => [], 'Core Functions' => [], 'Support Functions' => [], 'Unclassified Targets' => []];
        $weights = [];
        $evidence = collect();
        if ($report->office_target_id) {
            $draft = TargetDocumentDraft::where('office_target_id', $report->office_target_id)->first();
            $label = $draft?->proposal['weight_budget']['label'] ?? '';
            $sections[$this->section($label)][] = $rows[0];
            $weights[$this->section($label)][] = $report->officeTarget?->weight;
            $evidence = TargetMov::where('office_target_id', $report->office_target_id)
                ->where('user_id', $report->employee_id)->orderBy('id')->get()
                ->map(fn ($mov) => ['name' => $mov->original_name, 'description' => $mov->accomplishment]);
        } else {
            foreach ($targets->values() as $index => $target) {
                $label = $target->scope_metadata['category'] ?? $target->scope_metadata['weight_budget']['label'] ?? '';
                $section = $this->section($label);
                $sections[$section][] = $rows[$index];
                $weights[$section][] = $target->weight;
                $evidence = $evidence->concat($target->movs()->where('uploaded_by', $report->employee_id)->orderBy('id')->get()
                    ->map(fn ($mov) => ['name' => $mov->original_name, 'description' => $mov->description]));
                if ($target->commitment_plan_id) {
                    $evidence = $evidence->concat($target->outputs()->where('submitted_by', $report->employee_id)->get()
                        ->map(fn ($output) => ['name' => $output->evidence['name'] ?? 'Evidence', 'description' => $output->details]));
                }
            }
        }
        return [
            'employee' => $employee?->name ?? '',
            'position' => $employee?->positionTitle?->title ?? '',
            'office' => $office?->name ?? '',
            'reviewer' => $reviewer?->name ?? '',
            'reviewer_position' => $reviewer?->positionTitle?->title ?? ($reviewer?->hasRole('Planning Officer') ? 'Planning Officer' : ''),
            'approver' => $approver?->name ?? '',
            'approver_position' => $approver?->positionTitle?->title ?? '',
            'approver_office' => $approver?->office?->name ?? '',
            'approval_date' => $report->approved_at?->format('F j, Y') ?? '',
            'sections' => $sections,
            'weights' => collect($weights)->map(fn ($items) => in_array(null, $items, true) ? null : array_sum($items))->all(),
            'average' => $report->average_rating === null ? '' : number_format((float) $report->average_rating, 2),
            'comments' => $report->rating_remarks ?? '',
            'evidence' => $evidence->values()->all(),
        ];
    }

    private function section(string $label): string
    {
        $label = strtolower($label);
        return match (true) {
            str_contains($label, 'strategic') => 'Strategic Objectives',
            str_contains($label, 'core') => 'Core Functions',
            str_contains($label, 'support') => 'Support Functions',
            default => 'Unclassified Targets',
        };
    }
}
