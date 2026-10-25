<?php

namespace App\Http\Controllers;

use App\Models\Dpcr;
use App\Models\Ipcr;
use App\Models\Opcr;
use App\Models\Upcr;
use Barryvdh\DomPDF\Facade\Pdf;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\Response;
use Illuminate\View\View;
use Symfony\Component\HttpFoundation\StreamedResponse;

class ReportController extends Controller
{
    private const MODELS = [
        'opcr' => Opcr::class,
        'dpcr' => Dpcr::class,
        'upcr' => Upcr::class,
        'ipcr' => Ipcr::class,
    ];

    /**
     * @var array<string, string>
     */
    private array $titles = [
        'opcr' => 'OPCR Report',
        'dpcr' => 'DPCR Report',
        'upcr' => 'UPCR Report',
        'ipcr' => 'IPCR Report',
    ];

    public function index(): View
    {
        $myReportTypes = $this->myReportTypes();
        $myReports = collect($myReportTypes)->mapWithKeys(function ($label, $type) {
            $model = self::MODELS[$type];
            $query = $model::query()->where('prepared_by', request()->user()->id);
            if ($type === 'ipcr') {
                $query->orWhere('employee_id', request()->user()->id);
            }

            return [$type => $query->latest('updated_at')->paginate(10, ['*'], $type.'_page')->withQueryString()];
        });

        $reports = request()->user()->usesMyReports() ? ['ipcr' => 'My IPCR Report'] : $this->titles;
        $selectedType = request()->query('type');
        abort_if($selectedType !== null && (! is_string($selectedType) || ! array_key_exists($selectedType, $reports)), 404);

        $finishedCounts = [];
        $recent = collect();
        foreach ($reports as $type => $title) {
            $query = $this->finishedQuery($type);
            $finishedCounts[$type] = (clone $query)->count();
            if ($selectedType === null) {
                $recent = $recent->concat((clone $query)->with(['preparedBy', 'approvedBy'])
                    ->latest('updated_at')->limit(5)->get()->map(fn ($record) => $this->archiveRow($type, $record)));
            }
        }

        $finishedReports = $selectedType === null
            ? $recent->sortByDesc('approved_at')->take(12)->values()
            : $this->finishedQuery($selectedType)->with(['preparedBy', 'approvedBy'])
                ->latest('updated_at')->paginate(15)->withQueryString()
                ->through(fn ($record) => $this->archiveRow($selectedType, $record));

        return view('reports.index', [
            'reports' => $reports,
            'myReportTypes' => $myReportTypes,
            'myReports' => $myReports,
            'finishedCounts' => $finishedCounts,
            'finishedReports' => $finishedReports,
            'selectedType' => $selectedType,
            'canExport' => $this->canExport(),
        ]);
    }

    private function myReportTypes(): array
    {
        $user = request()->user();
        $types = [];
        if ($user->hasRole('Campus Director')) {
            $types['opcr'] = 'Office Performance Commitment and Review';
        }
        if ($user->hasAnyRole(['Supervisor', 'HR Head'])) {
            $types['dpcr'] = 'Division Performance Commitment and Review';
        }
        if ($user->hasRole('Supervisor')) {
            $types['upcr'] = 'Unit Performance Commitment and Review';
        }
        $types['ipcr'] = 'Individual Performance Commitment and Review';

        return $types;
    }

    public function finished(string $type, int $report): View
    {
        return view('reports.show', $this->reportData($type, false, $report));
    }

    public function finishedPdf(string $type, int $report): Response
    {
        $data = $this->reportData($type, true, $report);
        return Pdf::loadView('reports.exports.pdf', $data)->setPaper('a4', 'landscape')
            ->download($this->filename($type, 'pdf', $report));
    }

    public function finishedExcel(string $type, int $report): StreamedResponse
    {
        $data = $this->reportData($type, true, $report);
        return response()->streamDownload(function () use ($data): void {
            echo view('reports.exports.excel', $data)->render();
        }, $this->filename($type, 'xls', $report), [
            'Content-Type' => 'application/vnd.ms-excel; charset=UTF-8',
        ]);
    }

    public function show(string $type): View
    {
        $report = $this->reportData($type);

        return view('reports.show', $report);
    }

    public function pdf(string $type): Response
    {
        $report = $this->reportData($type, true);
        $pdf = Pdf::loadView('reports.exports.pdf', $report)->setPaper('a4', 'landscape');

        return $pdf->download($this->filename($type, 'pdf'));
    }

    public function excel(string $type): StreamedResponse
    {
        $report = $this->reportData($type, true);

        return response()->streamDownload(function () use ($report): void {
            echo view('reports.exports.excel', $report)->render();
        }, $this->filename($type, 'xls'), [
            'Content-Type' => 'application/vnd.ms-excel; charset=UTF-8',
        ]);
    }

    /**
     * @return array{type: string, title: string, generatedAt: \Illuminate\Support\Carbon, headers: array<int, string>, rows: array<int, array<int, string>>, forms: array<int, array<string, mixed>>, formHeaders: array<int, string>}
     */
    private function reportData(string $type, bool $export = false, ?int $finishedId = null): array
    {
        abort_unless(array_key_exists($type, $this->titles), 404);
        abort_if(request()->user()->usesMyReports() && $type !== 'ipcr', 403);

        $query = $this->records($type);
        if ($finishedId !== null) {
            $model = self::MODELS[$type];
            $query->whereKey($finishedId)->where('status', $model::STATUS_APPROVED);
        }
        $records = $query->get();
        abort_if($finishedId !== null && $records->isEmpty(), 404);
        if ($export && request()->user()->usesMyReports()) {
            $records = $records->filter(fn ($record) => $record->isApproved());
            abort_if($records->isEmpty(), 403, 'Your IPCR must be approved before exporting.');
        }

        return [
            'type' => $type,
            'canExport' => $finishedId !== null || $this->canExport(),
            'archiveId' => $finishedId,
            'title' => $this->titles[$type],
            'generatedAt' => now(),
            'headers' => $this->headers(),
            'rows' => $records->map(fn ($record): array => $this->row($type, $record))->values()->all(),
            'forms' => $records->map(fn ($record): array => $this->form($type, $record))->values()->all(),
            'formHeaders' => $this->formHeaders($type),
        ];
    }

    private function records(string $type): Builder
    {
        return match ($type) {
            'opcr' => Opcr::with(['evaluationCycle', 'office', 'preparedBy', 'approvedBy', 'performanceTargets.accomplishments', 'performanceTargets.evaluations'])
                ->latest(),
            'dpcr' => Dpcr::with(['opcr.evaluationCycle', 'opcr.office', 'employee.office', 'preparedBy', 'approvedBy', 'performanceTargets.accomplishments', 'performanceTargets.evaluations'])
                ->latest(),
            'upcr' => Upcr::with(['dpcr.opcr.evaluationCycle', 'dpcr.opcr.office', 'employee.office', 'preparedBy', 'performanceTargets.accomplishments', 'performanceTargets.evaluations'])
                ->latest(),
            'ipcr' => Ipcr::with(['upcr.dpcr.opcr.evaluationCycle', 'upcr.dpcr.opcr.office', 'employee.office', 'preparedBy', 'approvedBy', 'performanceTargets.accomplishments', 'performanceTargets.evaluations'])
                ->when(request()->user()->usesMyReports(), fn ($query) => $query->where('employee_id', request()->user()->id))
                ->latest(),
        };
    }

    private function finishedQuery(string $type): Builder
    {
        $model = self::MODELS[$type];
        return $model::query()
            ->where('status', $model::STATUS_APPROVED)
            ->when($type === 'ipcr' && request()->user()->usesMyReports(),
                fn ($query) => $query->where('employee_id', request()->user()->id));
    }

    private function archiveRow(string $type, object $record): array
    {
        return [
            'type' => $type,
            'id' => $record->id,
            'title' => $record->title,
            'prepared_by' => $record->preparedBy?->name ?? 'Not recorded',
            'approved_by' => $record->approvedBy?->name ?? 'Not recorded',
            'approved_at' => $record->approved_at ?? $record->updated_at,
        ];
    }

    private function canExport(): bool
    {
        return ! request()->user()->usesMyReports() || Ipcr::where('employee_id', request()->user()->id)->where('status', Ipcr::STATUS_APPROVED)->exists();
    }

    /**
     * @return array<int, string>
     */
    private function headers(): array
    {
        return [
            'Form',
            'Title',
            'Cycle',
            'Office',
            'Employee',
            'Status',
            'Prepared By',
            'Approved By',
            'Targets',
            'Accomplishments',
            'Average Completion',
            'Average Evaluation',
        ];
    }

    /**
     * @return array<int, string>
     */
    private function row(string $type, object $record): array
    {
        $targets = $record->performanceTargets;
        $accomplishments = $targets->flatMap->accomplishments;
        $evaluations = $targets->flatMap->evaluations;

        return [
            strtoupper($type),
            (string) $record->title,
            $this->cycleLabel($type, $record),
            $this->officeLabel($type, $record),
            $this->employeeLabel($type, $record),
            (string) ($record->status ?? 'active'),
            (string) ($record->preparedBy?->name ?? 'Not recorded'),
            (string) ($record->approvedBy?->name ?? 'Not approved'),
            (string) $targets->count(),
            (string) $accomplishments->count(),
            $accomplishments->count() > 0 ? number_format((float) $accomplishments->avg('completion_percentage'), 2).'%' : '0.00%',
            $evaluations->count() > 0 ? number_format((float) $evaluations->avg('computed_rating'), 2) : '0.00',
        ];
    }

    private function cycleLabel(string $type, object $record): string
    {
        $cycle = match ($type) {
            'opcr' => $record->evaluationCycle,
            'dpcr' => $record->opcr?->evaluationCycle,
            'upcr' => $record->dpcr?->opcr?->evaluationCycle,
            'ipcr' => $record->officeTarget?->evaluationCycle ?? $record->upcr?->dpcr?->opcr?->evaluationCycle,
        };

        return $cycle ? $cycle->year.' - '.$cycle->semester : 'No cycle';
    }

    private function officeLabel(string $type, object $record): string
    {
        $office = match ($type) {
            'opcr' => $record->office,
            'dpcr' => $record->opcr?->office,
            'upcr' => $record->dpcr?->opcr?->office,
            'ipcr' => $record->employee?->office ?? $record->upcr?->dpcr?->opcr?->office,
        };

        return $office?->name ?? 'No office';
    }

    private function employeeLabel(string $type, object $record): string
    {
        return match ($type) {
            'opcr' => 'Office level',
            'dpcr', 'upcr', 'ipcr' => $record->employee?->name ?? 'No employee',
        };
    }

    private function filename(string $type, string $extension, ?int $report = null): string
    {
        return $type.'-report'.($report === null ? '' : '-'.$report).'-'.now()->format('Y-m-d').'.'.$extension;
    }

    /**
     * @return array<string, mixed>
     */
    private function form(string $type, object $record): array
    {
        $rows = $record->performanceTargets->map(fn ($target): array => $this->formRow($type, $record, $target))->values()->all();
        if ($type === 'ipcr' && $record->office_target_id) {
            $details = \App\Models\TargetMov::where('office_target_id', $record->office_target_id)->where('user_id', $record->employee_id)
                ->orderBy('created_at')->pluck('accomplishment')->unique()->implode("\n\n");
            $rating = fn ($value) => $value === null ? '' : number_format((float) $value, 2);
            $rows = [[$record->success_indicators, '', $details, $rating($record->quality_rating), $rating($record->efficiency_rating), $rating($record->timeliness_rating), $rating($record->average_rating), $record->rating_remarks ?? '']];
        }
        return [
            'heading' => $this->formHeading($type),
            'commitment' => $this->commitmentStatement($type, $record),
            'prepared_name' => (string) ($record->preparedBy?->name ?? $this->employeeLabel($type, $record)),
            'prepared_position' => $this->preparedPosition($type, $record),
            'reviewed_by' => $type === 'ipcr' ? ($record->office_target_id ? $record->approvedBy?->name ?? 'Reviewer' : $record->upcr?->employee?->name ?? 'Reviewer') : null,
            'approved_by' => (string) ($record->approvedBy?->name ?? $this->defaultApprover($type)),
            'approved_position' => $this->approvedPosition($type),
            'rating_scale' => ['5 - Outstanding', '4 - Very Satisfactory', '3 - Satisfactory', '2 - Unsatisfactory', '1 - Poor'],
            'rows' => $rows,
        ];
    }

    /**
     * @return array<int, string>
     */
    private function formHeaders(string $type): array
    {
        return match ($type) {
            'dpcr' => ['Objectives', 'Program/Project/Activity (PPA)', 'Success Indicators (Targets + Measures)', 'Allotted Budget', 'Division/Individuals Accountable', 'Accomplishments', 'Q', 'E', 'T', 'A', 'Remarks'],
            'upcr' => ['MFO/PAP', 'Success Indicators (Targets + Measures)', 'Allotted Budget', 'Division/Individuals Accountable', 'Actual Accomplishment', 'Q', 'E', 'T', 'A', 'Remarks'],
            'ipcr' => ['Success Indicator (Target + Measure)', 'Allotted Budget', 'Actual Accomplishments', 'Q', 'E', 'T', 'A', 'Remarks'],
            default => ['Objectives', 'Program/Project/Activity (PPA)', 'Success Indicators (Targets + Measures)', 'Allotted Budget', 'Office/Individuals Accountable', 'Accomplishments', 'Q', 'E', 'T', 'A', 'Remarks'],
        };
    }

    /**
     * @return array<int, string>
     */
    private function formRow(string $type, object $record, object $target): array
    {
        $latestAccomplishment = $target->accomplishments->sortByDesc('created_at')->first();
        $latestEvaluation = $target->evaluations->sortByDesc('created_at')->first();
        $quality = $latestEvaluation ? number_format((float) ($latestEvaluation->quality_rating ?? $latestEvaluation->rating), 2) : '';
        $efficiency = $latestEvaluation ? number_format((float) ($latestEvaluation->efficiency_rating ?? $latestEvaluation->rating), 2) : '';
        $timeliness = $latestEvaluation ? number_format((float) ($latestEvaluation->timeliness_rating ?? $latestEvaluation->rating), 2) : '';
        $average = $latestEvaluation ? number_format((float) $latestEvaluation->rating, 2) : '';
        $computed = $latestEvaluation ? number_format((float) $latestEvaluation->computed_rating, 2) : '';
        $remarks = trim(($latestEvaluation?->remarks ?? '').' '.($latestEvaluation?->adjectival_rating ? '['.$latestEvaluation->adjectival_rating.']' : ''));

        if ($type === 'ipcr') {
            return [
                (string) $target->success_indicator,
                '',
                (string) ($latestAccomplishment?->details ?? ''),
                $quality,
                $efficiency,
                $timeliness,
                $average,
                $remarks,
            ];
        }

        $base = [
            (string) $target->target,
            (string) $target->success_indicator,
            '',
            $this->employeeLabel($type, $record),
            (string) ($latestAccomplishment?->details ?? ''),
            $quality,
            $efficiency,
            $timeliness,
            $average,
            $remarks,
        ];

        if ($type === 'upcr') {
            return $base;
        }

        return [
            (string) $target->target,
            (string) ($record->title ?? ''),
            (string) $target->success_indicator,
            '',
            $this->employeeLabel($type, $record),
            (string) ($latestAccomplishment?->details ?? ''),
            $quality,
            $efficiency,
            $timeliness,
            $average,
            $remarks,
        ];
    }

    private function formHeading(string $type): string
    {
        return match ($type) {
            'dpcr' => 'A. DIVISION PERFORMANCE COMMITMENT AND REVIEW (DPCR)',
            'upcr' => 'UNIT PERFORMANCE COMMITMENT AND REVIEW (UPCR)',
            'ipcr' => 'INDIVIDUAL PERFORMANCE COMMITMENT AND REVIEW (IPCR)',
            default => 'OFFICE PERFORMANCE COMMITMENT AND REVIEW (OPCR)',
        };
    }

    private function commitmentStatement(string $type, object $record): string
    {
        $name = $type === 'opcr' ? $record->preparedBy?->name : $this->employeeLabel($type, $record);
        $role = $this->preparedPosition($type, $record);

        return 'I, '.($name ?: 'employee').', '.$role.', commit to deliver and agree to be rated on the attainment of the following targets in accordance with the indicated measures for the period '.$this->periodLabel($type, $record).'.';
    }

    private function periodLabel(string $type, object $record): string
    {
        $cycle = match ($type) {
            'opcr' => $record->evaluationCycle,
            'dpcr' => $record->opcr?->evaluationCycle,
            'upcr' => $record->dpcr?->opcr?->evaluationCycle,
            'ipcr' => $record->officeTarget?->evaluationCycle ?? $record->upcr?->dpcr?->opcr?->evaluationCycle,
        };

        if (! $cycle) {
            return 'the selected evaluation period';
        }

        $months = str_contains(strtolower($cycle->semester), '1st') ? 'January to June' : 'July to December';

        return $months.' '.$cycle->year;
    }

    private function preparedPosition(string $type, object $record): string
    {
        return match ($type) {
            'opcr' => 'Office Head of '.$this->officeLabel($type, $record),
            'dpcr' => 'Division/College personnel of '.$this->officeLabel($type, $record),
            'upcr' => 'Unit personnel of '.$this->officeLabel($type, $record),
            'ipcr' => 'Employee of '.$this->officeLabel($type, $record),
        };
    }

    private function defaultApprover(string $type): string
    {
        return match ($type) {
            'dpcr' => 'Vice President / Campus Director',
            'upcr' => 'College Dean / Office Head',
            'ipcr' => 'College Dean / Office Head',
            default => 'Campus Director',
        };
    }

    private function approvedPosition(string $type): string
    {
        return match ($type) {
            'dpcr' => 'Vice President for Academic Affairs',
            'upcr' => 'College Dean',
            'ipcr' => 'College Dean',
            default => 'Campus Director',
        };
    }
}
