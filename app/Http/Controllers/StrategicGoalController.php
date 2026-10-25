<?php

namespace App\Http\Controllers;

use App\Models\StrategicGoal;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class StrategicGoalController extends Controller
{
    public function index(Request $request): View
    {
        $goals = StrategicGoal::with(['objectives', 'createdBy'])->latest();
        if (! $request->user()->hasRole('Administrator')) $goals->where('created_by', $request->user()->id);

        return view('strategic-goals.index', ['goals' => $goals->paginate(10)]);
    }

    public function create(): View
    {
        return view('strategic-goals.form', ['goal' => null]);
    }

    public function store(Request $request): RedirectResponse
    {
        $goal = StrategicGoal::create([...$this->validatedData($request), 'description' => '', 'created_by' => $request->user()->id]);
        return redirect()->route('strategic-objectives.create', $goal)->with('status', 'Strategic goal created. Add its first objective.');
    }

    public function edit(Request $request, StrategicGoal $strategicGoal): View
    {
        $this->authorizeManager($request, $strategicGoal);
        return view('strategic-goals.form', ['goal' => $strategicGoal]);
    }

    public function update(Request $request, StrategicGoal $strategicGoal): RedirectResponse
    {
        $this->authorizeManager($request, $strategicGoal);
        $strategicGoal->update($this->validatedData($request));
        return redirect()->route('strategic-goals.index')->with('status', 'Strategic goal updated successfully.');
    }

    public function destroy(Request $request, StrategicGoal $strategicGoal): RedirectResponse
    {
        $this->authorizeManager($request, $strategicGoal);
        $strategicGoal->delete();
        return redirect()->route('strategic-goals.index')->with('status', 'Strategic goal deleted successfully.');
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
