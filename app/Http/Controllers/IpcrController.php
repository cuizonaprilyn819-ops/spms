<?php

namespace App\Http\Controllers;

use App\Models\Ipcr;
use App\Models\OfficeTarget;
use App\Models\Upcr;
use App\Notifications\ApprovalNotification;
use App\Notifications\ReportWorkflowNotification;
use App\Services\CommitmentPlanning;
use App\Services\PlanningOrganization;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Illuminate\View\View;

class IpcrController extends Controller
{
    public function index(): View
    {
        $query = Ipcr::with(['upcr.dpcr.opcr.evaluationCycle', 'upcr.dpcr.opcr.office', 'employee', 'preparedBy', 'approvedBy'])->where(fn ($q) => $q->whereDoesntHave('commitmentPlan')->orWhereHas('commitmentPlan', fn ($p) => $p->whereIn('id', app(PlanningOrganization::class)->visiblePlanIds(request()->user()))));
        $user = request()->user();
        if ($user->hasRole('Supervisor')) {
            $query->whereHas('employee', fn ($q) => $q->where('office_id', $user->office_id));
        }
        if ($user->hasRole('Campus Director') && $user->office?->campus) {
            $query->whereHas('employee.office', fn ($q) => $q->where('campus', $user->office->campus));
        }
        if (! $user->hasAnyRole(['Administrator', 'HR', 'HR Head', 'HRM Office II', 'Campus Director', 'Supervisor'])) {
            $query->where('employee_id', $user->id);
        }
        $ipcrs = $query
            ->latest()
            ->paginate(10);

        return view('ipcrs.index', compact('ipcrs'));
    }

    public function create(): View
    {
        abort_unless(request()->user()->office_id, 403, 'An office assignment is required before preparing an IPCR.');
        $sourceTarget = null;
        if (request()->filled('office_target_id')) {
            $sourceTarget = OfficeTarget::findOrFail(request()->integer('office_target_id'));
            abort_unless($sourceTarget->assignedEmployees()->whereKey(request()->user()->id)->wherePivotNotNull('completed_at')->exists(), 403);
        }

        return view('ipcrs.create', [
            'sourceTarget' => $sourceTarget,
            'upcrs' => Upcr::with(['dpcr.opcr.evaluationCycle', 'dpcr.opcr.office', 'employee'])
                ->whereHas('dpcr.opcr', fn ($query) => $query->where('office_id', request()->user()->office_id))
                ->where('status', Upcr::STATUS_APPROVED)
                ->orderByDesc('created_at')
                ->get(),
            'employees' => request()->user()->reportAssignees()->with('office')->orderBy('name')->get(),
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        if ($request->filled('office_target_id')) {
            $data = $request->validate(['office_target_id' => ['required', 'integer'], 'title' => ['required', 'string', 'max:255'], 'success_indicators' => ['required', 'string', 'max:5000'], 'targets' => ['nullable', 'string', 'max:5000']]);
            $target = OfficeTarget::findOrFail($data['office_target_id']);
            CommitmentPlanning::legacyOnly($target->evaluationCycle);
            abort_unless($target->assignedEmployees()->whereKey($request->user()->id)->wherePivotNotNull('completed_at')->exists(), 403);
            $ipcr = Ipcr::firstOrCreate(['office_target_id' => $target->id, 'employee_id' => $request->user()->id], [...$data, 'prepared_by' => $request->user()->id, 'status' => Ipcr::STATUS_DRAFT]);

            return redirect()->route('ipcrs.show', $ipcr)->with('status', 'IPCR prepared from your completed target.');
        }
        abort_unless($request->user()->office_id, 403, 'An office assignment is required before preparing an IPCR.');
        if (! $request->user()->hasRole('Supervisor')) {
            $request->merge(['employee_id' => $request->user()->id]);
        }
        $data = $request->validate([
            'upcr_id' => ['required', 'integer', Rule::exists('upcrs', 'id')],
            'employee_id' => ['required', 'integer', Rule::exists('users', 'id')],
            'title' => ['required', 'string', 'max:255'],
            'success_indicators' => ['nullable', 'string', 'max:5000'],
            'targets' => ['nullable', 'string', 'max:5000'],
        ]);

        $upcr = Upcr::with('dpcr.opcr')->findOrFail($data['upcr_id']);
        CommitmentPlanning::legacyOnly($upcr->dpcr->opcr->evaluationCycle);
        abort_unless($request->user()->reportAssignees()->whereKey($data['employee_id'])->exists(), 403);
        abort_unless((int) $upcr->dpcr->opcr->office_id === (int) $request->user()->office_id && $upcr->isApproved(), 403);

        $request->validate([
            'employee_id' => [
                Rule::unique('ipcrs', 'employee_id')
                    ->where('upcr_id', $request->integer('upcr_id')),
            ],
        ]);

        $ipcr = Ipcr::create([
            ...$data,
            'status' => Ipcr::STATUS_DRAFT,
            'prepared_by' => $request->user()->id,
        ]);

        return redirect()
            ->route('ipcrs.show', $ipcr)
            ->with('status', 'IPCR created successfully.');
    }

    public function show(Ipcr $ipcr): View|RedirectResponse
    {
        if ($ipcr->commitmentPlan) {
            abort_unless(app(PlanningOrganization::class)->canView(request()->user(), $ipcr->commitmentPlan), 403);

            return redirect()->route('planning.show', $ipcr->commitmentPlan);
        }
        if (request()->user()->usesMyReports()) {
            abort_unless((int) $ipcr->employee_id === (int) request()->user()->id, 403);
        }

        return view('ipcrs.show', [
            'ipcr' => $ipcr->load(['upcr.dpcr.opcr.evaluationCycle', 'upcr.dpcr.opcr.office', 'employee.office', 'preparedBy', 'approvedBy', 'statusHistories.actor']),
        ]);
    }

    public function approve(Request $request, Ipcr $ipcr): RedirectResponse
    {
        if ($ipcr->office_target_id) {
            return app(ReportWorkflowController::class)->approve($request, 'ipcr', $ipcr->id);
        }
        abort_unless($request->user()->hasAnyRole(['Administrator', 'Campus Director', 'Supervisor']), 403);

        if ($ipcr->isApproved()) {
            return redirect()
                ->route('ipcrs.show', $ipcr)
                ->with('status', 'IPCR is already approved.');
        }

        $ipcr->update([
            'status' => Ipcr::STATUS_APPROVED,
            'approved_by' => $request->user()->id,
            'approved_at' => now(),
        ]);

        $ipcr->preparedBy?->notify(new ApprovalNotification('IPCR', $ipcr));
        if ((int) $ipcr->employee_id !== (int) $ipcr->prepared_by) {
            $ipcr->employee?->notify(new ReportWorkflowNotification('IPCR', $ipcr, 'approved'));
        }

        return redirect()
            ->route('ipcrs.show', $ipcr)
            ->with('status', 'IPCR approved successfully.');
    }
}
