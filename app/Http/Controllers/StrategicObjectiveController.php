<?php

namespace App\Http\Controllers;

use App\Models\StrategicGoal;
use App\Models\StrategicObjective;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class StrategicObjectiveController extends Controller
{
    public function create(Request $request, StrategicGoal $strategicGoal): View
    {
        $this->authorizeManager($request, $strategicGoal);
        $strategicGoal->load('objectives.officeTargets');
        return view('strategic-objectives.form', ['goal' => $strategicGoal, 'objective' => null]);
    }

    public function store(Request $request, StrategicGoal $strategicGoal): RedirectResponse
    {
        $this->authorizeManager($request, $strategicGoal);
        $strategicGoal->objectives()->create($this->validatedData($request));
        return redirect()->route('strategic-objectives.create', $strategicGoal)->with('status', 'Objective added. Add another objective or add targets to an objective below.');
    }

    public function update(Request $request, StrategicObjective $strategicObjective): RedirectResponse
    {
        $this->authorizeManager($request, $strategicObjective->strategicGoal);
        $strategicObjective->update($this->validatedData($request));
        return redirect()->route('strategic-goals.index')->with('status', 'Strategic objective updated successfully.');
    }

    public function edit(Request $request, StrategicObjective $strategicObjective): View
    {
        $this->authorizeManager($request, $strategicObjective->strategicGoal);
        $strategicObjective->strategicGoal->load('objectives.officeTargets');
        return view('strategic-objectives.form', ['goal' => $strategicObjective->strategicGoal, 'objective' => $strategicObjective]);
    }

    public function destroy(Request $request, StrategicObjective $strategicObjective): RedirectResponse
    {
        $this->authorizeManager($request, $strategicObjective->strategicGoal);
        $strategicObjective->delete();
        return back()->with('status', 'Strategic objective deleted successfully.');
    }

    private function validatedData(Request $request): array
    {
        return $request->validate(['title' => ['required', 'string', 'max:255']]);
    }

    private function authorizeManager(Request $request, StrategicGoal $goal): void
    {
        abort_unless($request->user()->hasRole('Administrator') || $goal->created_by === $request->user()->id, 403);
    }
}
