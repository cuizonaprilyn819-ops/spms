<?php

namespace App\Http\Controllers;

use App\Models\Opcr;
use App\Notifications\ApprovalNotification;
use App\Services\PlanningOrganization;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class OpcrController extends Controller
{
    public function index(): View
    {
        $query = Opcr::with(['evaluationCycle', 'office', 'preparedBy', 'approvedBy'])->where(fn ($q) => $q->whereDoesntHave('commitmentPlan')->orWhereHas('commitmentPlan', fn ($p) => $p->whereIn('id', app(PlanningOrganization::class)->visiblePlanIds(request()->user()))));

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
        return view('opcrs.create', [
            'cycles' => \App\Models\EvaluationCycle::where('status', 'open')->orderByDesc('year')->get(),
            'offices' => \App\Models\Office::forCampus()->whereKey(request()->user()->office_id)->get(),
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        abort_unless($request->user()->hasRole('Campus Director') && $request->user()->office_id, 403);
        $data = $request->validate([
            'evaluation_cycle_id' => ['required', 'integer', 'exists:evaluation_cycles,id'],
            'office_id' => ['required', 'integer', \Illuminate\Validation\Rule::exists('offices', 'id')->where('is_active', true)->where('campus', config('hinunangan.campus_id'))],
            'title' => ['required', 'string', 'max:255'],
            'success_indicators' => ['nullable', 'string', 'max:5000'],
            'targets' => ['nullable', 'string', 'max:5000'],
        ]);
        abort_unless((int) $data['office_id'] === (int) $request->user()->office_id, 403, 'Prepare an OPCR for your assigned office only.');
        $opcr = \Illuminate\Support\Facades\DB::transaction(function () use ($data, $request) {
            $cycle = \App\Models\EvaluationCycle::whereKey($data['evaluation_cycle_id'])->lockForUpdate()->firstOrFail();
            \App\Services\CommitmentPlanning::legacyOnly($cycle);
            $cycle->ensurePcrCreationOpen();
            $request->validate(['office_id' => [\Illuminate\Validation\Rule::unique('opcrs', 'office_id')->where('evaluation_cycle_id', $cycle->id)]]);
            return Opcr::create([...$data, 'status' => Opcr::STATUS_DRAFT, 'prepared_by' => $request->user()->id]);
        });
        return redirect()->route('opcrs.show', $opcr)->with('status', 'OPCR created successfully.');
    }

    public function show(Opcr $opcr): View|RedirectResponse
    {
        if ($opcr->commitmentPlan) {
            abort_unless(app(PlanningOrganization::class)->canView(request()->user(), $opcr->commitmentPlan), 403);

            return redirect()->route('planning.show', $opcr->commitmentPlan);
        }
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
        return $request->user()->hasAnyRole(['Supervisor', 'Division Head', 'Unit Head'])
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
