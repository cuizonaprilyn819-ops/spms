<?php

namespace App\Http\Controllers;

use App\Models\Dpcr;
use App\Models\Upcr;
use App\Services\CommitmentPlanning;
use App\Services\PlanningOrganization;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Illuminate\View\View;

class UpcrController extends Controller
{
    public function index(): View
    {
        $query = Upcr::with(['dpcr.opcr.evaluationCycle', 'dpcr.opcr.office', 'dpcr.employee', 'employee', 'preparedBy'])->where(fn ($q) => $q->whereDoesntHave('commitmentPlan')->orWhereHas('commitmentPlan', fn ($p) => $p->whereIn('id', app(PlanningOrganization::class)->visiblePlanIds(request()->user()))));
        $user = request()->user();
        if ($user->hasRole('Supervisor')) {
            $query->whereHas('employee', fn ($q) => $q->whereIn('office_id', app(\App\Services\TargetHierarchy::class)->managedOfficeIds($user)));
        }
        if ($user->hasRole('Campus Director') && $user->office?->campus) {
            $query->whereHas('employee.office', fn ($q) => $q->where('campus', $user->office->campus));
        }
        if ($user->hasRole('Employee')) {
            $query->where('employee_id', $user->id);
        }
        $upcrs = $query
            ->latest()
            ->paginate(10);

        return view('upcrs.index', compact('upcrs'));
    }

    public function create(): View
    {
        abort_unless(request()->user()->hasAnyRole(['Supervisor', 'HR Head']) && request()->user()->office_id, 403);

        return view('upcrs.create', [
            'dpcrs' => Dpcr::with(['opcr.evaluationCycle', 'opcr.office', 'employee'])
                ->whereHas('opcr', fn ($query) => $query->whereIn('office_id', app(\App\Services\PlanningOrganization::class)->ancestors(request()->user()->office)))
                ->whereHas('employee', fn ($query) => $query->whereIn('office_id', app(\App\Services\PlanningOrganization::class)->ancestors(request()->user()->office)))
                ->orderByDesc('created_at')
                ->get(),
            'employees' => request()->user()->reportAssignees()->with('office')->orderBy('name')->get(),
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        abort_unless($request->user()->hasAnyRole(['Supervisor', 'HR Head']) && $request->user()->office_id, 403);
        $data = $request->validate([
            'dpcr_id' => ['required', 'integer', Rule::exists('dpcrs', 'id')],
            'employee_id' => ['required', 'integer', Rule::exists('users', 'id')],
            'title' => ['required', 'string', 'max:255'],
            'success_indicators' => ['nullable', 'string', 'max:5000'],
            'targets' => ['nullable', 'string', 'max:5000'],
        ]);

        $dpcr = Dpcr::with('opcr')->findOrFail($data['dpcr_id']);
        CommitmentPlanning::legacyOnly($dpcr->opcr->evaluationCycle);
        abort_unless($request->user()->reportAssignees()->whereKey($data['employee_id'])->exists(), 403);
        $path = app(\App\Services\PlanningOrganization::class)->ancestors($request->user()->office);
        abort_unless(in_array((int) $dpcr->opcr->office_id, $path) && in_array((int) $dpcr->employee?->office_id, $path), 403);

        $request->validate([
            'employee_id' => [
                Rule::unique('upcrs', 'employee_id')
                    ->where('dpcr_id', $request->integer('dpcr_id')),
            ],
        ]);

        $dpcr->opcr->evaluationCycle->ensurePcrCreationOpen();
        $upcr = Upcr::create([
            ...$data,
            'prepared_by' => $request->user()->id,
        ]);

        \App\Models\OfficeTarget::where('evaluation_cycle_id', $dpcr->opcr->evaluation_cycle_id)->whereHas('offices', fn ($q) => $q->whereKey($request->user()->office_id))->each(fn ($target) => app(\App\Services\TargetHierarchy::class)->linkAvailableUpcrs($target));

        return redirect()
            ->route('upcrs.show', $upcr)
            ->with('status', 'UPCR created successfully.');
    }

    public function show(Upcr $upcr): View|RedirectResponse
    {
        if ($upcr->commitmentPlan) {
            abort_unless(app(PlanningOrganization::class)->canView(request()->user(), $upcr->commitmentPlan), 403);

            return redirect()->route('planning.show', $upcr->commitmentPlan);
        }

        $viewer = request()->user();
        if ($viewer->hasAnyRole(['Supervisor', 'HR Head']) && ! $viewer->hasAnyRole(['Administrator', 'Planning Officer', 'Campus Director'])) {
            abort_unless(in_array((int) $upcr->employee?->office_id, app(\App\Services\TargetHierarchy::class)->managedOfficeIds($viewer)), 403);
        }
        return view('upcrs.show', [
            'upcr' => $upcr->load(['dpcr.opcr.evaluationCycle', 'dpcr.opcr.office', 'dpcr.employee', 'employee.office', 'preparedBy', 'approvedBy', 'statusHistories.actor']),
        ]);
    }
}
