<?php

namespace App\Http\Controllers;

use App\Models\EvaluationCycle;
use App\Models\Office;
use App\Models\Opcr;
use App\Notifications\ApprovalNotification;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Illuminate\View\View;

class OpcrController extends Controller
{
    public function index(): View
    {
        $opcrs = Opcr::with(['evaluationCycle', 'office', 'preparedBy', 'approvedBy'])
            ->latest()
            ->paginate(10);

        return view('opcrs.index', compact('opcrs'));
    }

    public function create(): View
    {
        return view('opcrs.create', [
            'cycles' => EvaluationCycle::where('status', EvaluationCycle::STATUS_OPEN)
                ->orderByDesc('year')
                ->orderBy('semester')
                ->get(),
            'offices' => Office::orderBy('name')->get(),
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        $data = $request->validate([
            'evaluation_cycle_id' => [
                'required',
                'integer',
                Rule::exists('evaluation_cycles', 'id')
                    ->where('status', EvaluationCycle::STATUS_OPEN),
            ],
            'office_id' => ['required', 'integer', Rule::exists('offices', 'id')],
            'title' => ['required', 'string', 'max:255'],
            'success_indicators' => ['nullable', 'string', 'max:5000'],
            'targets' => ['nullable', 'string', 'max:5000'],
        ]);

        $request->validate([
            'office_id' => [
                Rule::unique('opcrs', 'office_id')
                    ->where('evaluation_cycle_id', $request->integer('evaluation_cycle_id')),
            ],
        ]);

        $opcr = Opcr::create([
            ...$data,
            'status' => Opcr::STATUS_DRAFT,
            'prepared_by' => $request->user()->id,
        ]);

        return redirect()
            ->route('opcrs.show', $opcr)
            ->with('status', 'OPCR created successfully.');
    }

    public function show(Opcr $opcr): View
    {
        return view('opcrs.show', [
            'opcr' => $opcr->load(['evaluationCycle', 'office', 'preparedBy', 'approvedBy']),
        ]);
    }

    public function approve(Request $request, Opcr $opcr): RedirectResponse
    {
        abort_unless($request->user()->hasAnyRole(['Administrator', 'Campus Director']), 403);

        if ($opcr->isApproved()) {
            return redirect()
                ->route('opcrs.show', $opcr)
                ->with('status', 'OPCR is already approved.');
        }

        $opcr->update([
            'status' => Opcr::STATUS_APPROVED,
            'approved_by' => $request->user()->id,
            'approved_at' => now(),
        ]);

        $opcr->preparedBy?->notify(new ApprovalNotification('OPCR', $opcr));

        return redirect()
            ->route('opcrs.show', $opcr)
            ->with('status', 'OPCR approved successfully.');
    }
}
