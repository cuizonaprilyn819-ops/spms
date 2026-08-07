<?php

namespace App\Http\Controllers;

use App\Models\Evaluation;
use App\Models\PerformanceTarget;
use App\Models\User;
use App\Notifications\EvaluationNotification;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Illuminate\View\View;

class EvaluationController extends Controller
{
    public function index(): View
    {
        $evaluations = Evaluation::with(['employee.office', 'performanceTarget.targetable', 'ratedBy'])
            ->latest()
            ->paginate(10);

        return view('evaluations.index', compact('evaluations'));
    }

    public function create(): View
    {
        return view('evaluations.create', [
            'employees' => User::with('office')
                ->role('Employee')
                ->orderBy('name')
                ->get(),
            'targets' => PerformanceTarget::with('targetable')
                ->latest()
                ->get(),
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        $data = $request->validate([
            'employee_id' => ['required', 'integer', Rule::exists('users', 'id')],
            'performance_target_id' => [
                'required',
                'integer',
                Rule::exists('performance_targets', 'id'),
                Rule::unique('evaluations', 'performance_target_id')
                    ->where(fn ($query) => $query->where('employee_id', $request->integer('employee_id'))),
            ],
            'quality_rating' => ['required', 'numeric', 'min:1', 'max:5'],
            'efficiency_rating' => ['required', 'numeric', 'min:1', 'max:5'],
            'timeliness_rating' => ['required', 'numeric', 'min:1', 'max:5'],
            'remarks' => ['nullable', 'string', 'max:5000'],
        ]);

        $target = PerformanceTarget::findOrFail($data['performance_target_id']);
        $average = Evaluation::computeAverage(
            (float) $data['quality_rating'],
            (float) $data['efficiency_rating'],
            (float) $data['timeliness_rating'],
        );

        $evaluation = Evaluation::create([
            ...$data,
            'rating' => $average,
            'computed_rating' => Evaluation::computeRating($average, (float) $target->weight),
            'adjectival_rating' => Evaluation::adjectivalRating($average),
            'rated_by' => $request->user()->id,
        ]);

        $evaluation->employee?->notify(new EvaluationNotification($evaluation->load('performanceTarget')));

        return redirect()
            ->route('evaluations.show', $evaluation)
            ->with('status', 'Employee evaluation saved successfully.');
    }

    public function show(Evaluation $evaluation): View
    {
        return view('evaluations.show', [
            'evaluation' => $evaluation->load(['employee.office', 'performanceTarget.targetable', 'ratedBy']),
        ]);
    }
}
