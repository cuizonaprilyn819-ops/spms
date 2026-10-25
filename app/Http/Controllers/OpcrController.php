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
        $query = Opcr::with(['evaluationCycle', 'office', 'preparedBy', 'approvedBy']);

        if ($this->isOfficeScoped(request())) {
            $query->where('office_id', request()->user()->office_id);
        }

        $opcrs = $query
            ->latest()
            ->paginate(10);

        return view('opcrs.index', compact('opcrs'));
    }

    public function create(): View
    {
        abort_unless(request()->user()->hasRole('Campus Director') && request()->user()->office_id, 403);
        $offices = Office::query()->whereKey(request()->user()->office_id);

        return view('opcrs.create', [
            'cycles' => EvaluationCycle::where('status', EvaluationCycle::STATUS_OPEN)
                ->orderByDesc('year')
                ->orderBy('semester')
                ->get(),
            'offices' => $offices->get(),
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        abort_unless($request->user()->hasRole('Campus Director') && $request->user()->office_id, 403);
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

        if ((int) $data['office_id'] !== (int) $request->user()->office_id) {
            abort(403, 'You may only create an OPCR for your assigned office.');
        }

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
        $this->authorizeOfficeAccess(request(), $opcr);

        return view('opcrs.show', [
            'opcr' => $opcr->load(['evaluationCycle', 'office', 'preparedBy', 'approvedBy', 'statusHistories.actor']),
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

    private function isOfficeScoped(Request $request): bool
    {
        return $request->user()->hasRole('Supervisor')
            && ! $request->user()->hasAnyRole(['Administrator', 'HR', 'HR Head', 'Campus Director'])
            && $request->user()->office_id !== null;
    }

    private function authorizeOfficeAccess(Request $request, Opcr $opcr): void
    {
        if ($this->isOfficeScoped($request)) {
            abort_unless((int) $opcr->office_id === (int) $request->user()->office_id, 403);
        }
    }
}
