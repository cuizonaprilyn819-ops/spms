<?php

namespace App\Http\Controllers;

use App\Models\EvaluationCycle;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;
use Illuminate\View\View;

class EvaluationCycleController extends Controller
{
    public function index(): View
    {
        $cycles = EvaluationCycle::orderByDesc('year')
            ->orderBy('semester')
            ->paginate(10);

        return view('evaluation-cycles.index', ['cycles' => $cycles, 'people' => \App\Models\User::whereHas('office', fn ($q) => $q->forCampus())->orderBy('name')->get()]);
    }

    public function create(): View
    {
        return view('evaluation-cycles.create', [
            'semesters' => EvaluationCycle::semesters(),
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        $data = $request->validate([
            'pcr_creation_closes_at' => ['nullable', 'required_with:pcr_submission_due_at', 'date_format:Y-m-d'],
            'pcr_submission_due_at' => ['nullable', 'required_with:pcr_creation_closes_at', 'date_format:Y-m-d', 'after_or_equal:pcr_creation_closes_at'],
            'pcr_reminder_days' => ['sometimes', 'integer', 'min:1', 'max:60'],
            'year' => ['required', 'integer', 'min:2000', 'max:2100'],
            'semester' => [
                'required',
                'string',
                Rule::in(EvaluationCycle::semesters()),
                Rule::unique('evaluation_cycles', 'semester')
                    ->where('year', $request->integer('year')),
            ],
        ]);

        foreach (['pcr_creation_closes_at', 'pcr_submission_due_at'] as $field) {
            if (! empty($data[$field])) $data[$field] = \Illuminate\Support\Carbon::parse($data[$field])->endOfDay();
        }
        DB::transaction(function () use ($data): void {
            EvaluationCycle::query()
                ->where('status', EvaluationCycle::STATUS_OPEN)
                ->update(['status' => EvaluationCycle::STATUS_CLOSED, 'closed_at' => now()]);

            EvaluationCycle::create([
                ...$data,
                'status' => EvaluationCycle::STATUS_OPEN,
                'opened_at' => now(),
            ]);
        });

        return redirect()
            ->route('evaluation-cycles.index')
            ->with('status', 'Evaluation period created and opened successfully.');
    }

    public function open(EvaluationCycle $evaluationCycle): RedirectResponse
    {
        DB::transaction(function () use ($evaluationCycle): void {
            EvaluationCycle::query()
                ->where('id', '!=', $evaluationCycle->id)
                ->where('status', EvaluationCycle::STATUS_OPEN)
                ->update([
                    'status' => EvaluationCycle::STATUS_CLOSED,
                    'closed_at' => now(),
                ]);

            $evaluationCycle->update([
                'status' => EvaluationCycle::STATUS_OPEN,
                'opened_at' => now(),
                'closed_at' => null,
            ]);
        });

        return redirect()
            ->route('evaluation-cycles.index')
            ->with('status', "{$evaluationCycle->label} is now open.");
    }

    public function chairperson(Request $request, EvaluationCycle $evaluationCycle): RedirectResponse
    {
        abort_unless($request->user()->hasAnyRole(['Administrator', 'Planning Officer']), 403);
        $data = $request->validate(['pmt_chairperson_id' => ['required', Rule::exists('users', 'id')->whereIn('office_id', \App\Models\Office::forCampus()->select('id'))]]);
        DB::transaction(function () use ($evaluationCycle, $data) {
            $cycle = EvaluationCycle::whereKey($evaluationCycle->id)->lockForUpdate()->firstOrFail();
            abort_unless($cycle->isOpen(), 422, 'Open the evaluation period before changing its PMT chairperson.');
            $before = ['pmt_chairperson_id' => $cycle->pmt_chairperson_id];
            $cycle->update($data);
            \App\Models\WorkflowAudit::record($cycle, 'PMT chairperson assigned', $before, $data);
        });
        return back()->with('status', 'PMT chairperson updated for this evaluation period.');
    }

    public function deadlines(Request $request, EvaluationCycle $evaluationCycle): RedirectResponse
    {
        abort_unless($request->user()->hasAnyRole(['Administrator', 'Planning Officer']), 403);
        $data = $request->validate([
            'pcr_creation_closes_at' => ['required', 'date_format:Y-m-d'],
            'pcr_submission_due_at' => ['required', 'date_format:Y-m-d', 'after_or_equal:pcr_creation_closes_at'],
            'pcr_reminder_days' => ['required', 'integer', 'min:1', 'max:60'],
        ]);
        foreach (['pcr_creation_closes_at', 'pcr_submission_due_at'] as $field) $data[$field] = \Illuminate\Support\Carbon::parse($data[$field])->endOfDay();
        DB::transaction(function () use ($evaluationCycle, $data) {
            $cycle = EvaluationCycle::whereKey($evaluationCycle->id)->lockForUpdate()->firstOrFail();
            abort_unless($cycle->isOpen(), 422, 'Open the evaluation period before changing PCR deadlines.');
            $before = $cycle->only(array_keys($data));
            $cycle->update($data);
            \App\Models\WorkflowAudit::record($cycle, 'PCR creation and submission deadlines updated', $before, $cycle->only(array_keys($data)));
        });
        return back()->with('status', 'PCR deadlines and reminder window saved. Existing first-submission records keep their original deadline snapshot.');
    }

    public function close(EvaluationCycle $evaluationCycle): RedirectResponse
    {
        $evaluationCycle->update([
            'status' => EvaluationCycle::STATUS_CLOSED,
            'closed_at' => now(),
        ]);

        return redirect()
            ->route('evaluation-cycles.index')
            ->with('status', "{$evaluationCycle->label} is now closed.");
    }
}
