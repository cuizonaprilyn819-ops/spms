<?php

namespace App\Http\Controllers;

use App\Models\Accomplishment;
use App\Models\PerformanceTarget;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Illuminate\View\View;

class AccomplishmentController extends Controller
{
    public function index(): View
    {
        $accomplishments = Accomplishment::with(['performanceTarget.targetable', 'submittedBy'])
            ->latest()
            ->paginate(10);

        return view('accomplishments.index', compact('accomplishments'));
    }

    public function create(): View
    {
        return view('accomplishments.create', [
            'targets' => PerformanceTarget::with('targetable')
                ->whereNull('commitment_plan_id')
                ->latest()
                ->get(),
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        $data = $this->validatedData($request);

        $accomplishment = Accomplishment::create([
            ...$data,
            'submitted_by' => $request->user()->id,
        ]);

        return redirect()
            ->route('accomplishments.show', $accomplishment)
            ->with('status', 'Accomplishment submitted successfully.');
    }

    public function show(Accomplishment $accomplishment): View
    {
        return view('accomplishments.show', [
            'accomplishment' => $accomplishment->load(['performanceTarget.targetable', 'submittedBy']),
        ]);
    }

    public function edit(Accomplishment $accomplishment): View
    {
        return view('accomplishments.edit', [
            'accomplishment' => $accomplishment->load(['performanceTarget.targetable']),
            'targets' => PerformanceTarget::with('targetable')
                ->latest()
                ->get(),
        ]);
    }

    public function update(Request $request, Accomplishment $accomplishment): RedirectResponse
    {
        $accomplishment->update($this->validatedData($request));

        return redirect()
            ->route('accomplishments.show', $accomplishment)
            ->with('status', 'Accomplishment updated successfully.');
    }

    /**
     * @return array{performance_target_id: int, details: string, completion_percentage: float|int|string}
     */
    private function validatedData(Request $request): array
    {
        abort_if(PerformanceTarget::find($request->input('performance_target_id'))?->commitment_plan_id, 422, 'Record commitment outputs with evidence in Commitment Planning.');

        return $request->validate([
            'performance_target_id' => ['required', 'integer', Rule::exists('performance_targets', 'id')],
            'details' => ['required', 'string', 'max:5000'],
            'completion_percentage' => ['required', 'numeric', 'min:0', 'max:100'],
        ]);
    }
}
