<?php

namespace App\Http\Controllers;

use App\Models\Dpcr;
use App\Models\Opcr;
use App\Models\User;
use App\Notifications\ApprovalNotification;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Illuminate\View\View;

class DpcrController extends Controller
{
    public function index(): View
    {
        $query = Dpcr::with(['opcr.evaluationCycle', 'opcr.office', 'employee', 'preparedBy', 'approvedBy']);

        if ($this->isOfficeScoped(request())) {
            $query->whereHas('opcr', fn ($opcr) => $opcr->where('office_id', request()->user()->office_id));
        }

        $dpcrs = $query
            ->latest()
            ->paginate(10);

        return view('dpcrs.index', compact('dpcrs'));
    }

    public function create(): View
    {
        abort_unless(request()->user()->hasAnyRole(['Supervisor', 'HR Head']) && request()->user()->office_id, 403);
        $opcrs = Opcr::with(['evaluationCycle', 'office'])->orderByDesc('created_at');
        $officeId = request()->user()->office_id;
        $opcrs->where('office_id', $officeId)->where('status', Opcr::STATUS_APPROVED);

        return view('dpcrs.create', [
            'opcrs' => $opcrs->get(),
            'employees' => User::where('office_id', $officeId)
                ->whereKeyNot(request()->user()->id)
                ->orderBy('name')->get(),
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        abort_unless($request->user()->hasAnyRole(['Supervisor', 'HR Head']) && $request->user()->office_id, 403);
        $data = $request->validate([
            'opcr_id' => ['required', 'integer', Rule::exists('opcrs', 'id')],
            'employee_id' => ['required', 'integer', Rule::exists('users', 'id')],
            'title' => ['required', 'string', 'max:255'],
            'success_indicators' => ['nullable', 'string', 'max:5000'],
            'targets' => ['nullable', 'string', 'max:5000'],
        ]);

        $opcr = Opcr::findOrFail($data['opcr_id']);
        $employee = User::findOrFail($data['employee_id']);

        $officeId = (int) $request->user()->office_id;
        abort_unless((int) $opcr->office_id === $officeId && (int) $employee->office_id === $officeId && $opcr->isApproved(), 403);

        $request->validate([
            'employee_id' => [
                Rule::unique('dpcrs', 'employee_id')
                    ->where('opcr_id', $request->integer('opcr_id')),
            ],
        ]);

        $dpcr = Dpcr::create([
            ...$data,
            'status' => Dpcr::STATUS_DRAFT,
            'prepared_by' => $request->user()->id,
        ]);

        return redirect()
            ->route('dpcrs.show', $dpcr)
            ->with('status', 'DPCR created successfully.');
    }

    public function show(Dpcr $dpcr): View
    {
        $this->authorizeOfficeAccess(request(), $dpcr);

        return view('dpcrs.show', [
            'dpcr' => $dpcr->load(['opcr.evaluationCycle', 'opcr.office', 'employee.office', 'preparedBy', 'approvedBy', 'statusHistories.actor']),
        ]);
    }

    public function approve(Request $request, Dpcr $dpcr): RedirectResponse
    {
        abort_unless($request->user()->hasAnyRole(['Administrator', 'Campus Director', 'Supervisor']), 403);

        $this->authorizeOfficeAccess($request, $dpcr);

        if ($dpcr->isApproved()) {
            return redirect()
                ->route('dpcrs.show', $dpcr)
                ->with('status', 'DPCR is already approved.');
        }

        $dpcr->update([
            'status' => Dpcr::STATUS_APPROVED,
            'approved_by' => $request->user()->id,
            'approved_at' => now(),
        ]);

        $dpcr->preparedBy?->notify(new ApprovalNotification('DPCR', $dpcr));

        return redirect()
            ->route('dpcrs.show', $dpcr)
            ->with('status', 'DPCR approved successfully.');
    }

    private function isOfficeScoped(Request $request): bool
    {
        return $request->user()->hasRole('Supervisor')
            && ! $request->user()->hasAnyRole(['Administrator', 'HR', 'HR Head', 'Campus Director'])
            && $request->user()->office_id !== null;
    }

    private function authorizeOfficeAccess(Request $request, Dpcr $dpcr): void
    {
        if ($this->isOfficeScoped($request)) {
            abort_unless((int) $dpcr->opcr()->value('office_id') === (int) $request->user()->office_id, 403);
        }
    }
}
