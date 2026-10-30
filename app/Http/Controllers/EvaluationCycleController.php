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

        return view('evaluation-cycles.index', compact('cycles'));
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
            'year' => ['required', 'integer', 'min:2000', 'max:2100'],
            'semester' => [
                'required',
                'string',
                Rule::in(EvaluationCycle::semesters()),
                Rule::unique('evaluation_cycles', 'semester')
                    ->where('year', $request->integer('year')),
            ],
        ], [], ['semester' => 'quarter']);

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
