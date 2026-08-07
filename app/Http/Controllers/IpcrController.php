<?php

namespace App\Http\Controllers;

use App\Models\Ipcr;
use App\Models\Upcr;
use App\Models\User;
use App\Notifications\ApprovalNotification;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Illuminate\View\View;

class IpcrController extends Controller
{
    public function index(): View
    {
        $ipcrs = Ipcr::with(['upcr.dpcr.opcr.evaluationCycle', 'upcr.dpcr.opcr.office', 'employee', 'preparedBy', 'approvedBy'])
            ->latest()
            ->paginate(10);

        return view('ipcrs.index', compact('ipcrs'));
    }

    public function create(): View
    {
        return view('ipcrs.create', [
            'upcrs' => Upcr::with(['dpcr.opcr.evaluationCycle', 'dpcr.opcr.office', 'employee'])
                ->orderByDesc('created_at')
                ->get(),
            'employees' => User::with(['office', 'roles'])
                ->orderBy('name')
                ->get(),
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        $data = $request->validate([
            'upcr_id' => ['required', 'integer', Rule::exists('upcrs', 'id')],
            'employee_id' => ['required', 'integer', Rule::exists('users', 'id')],
            'title' => ['required', 'string', 'max:255'],
            'success_indicators' => ['nullable', 'string', 'max:5000'],
            'targets' => ['nullable', 'string', 'max:5000'],
        ]);

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

    public function show(Ipcr $ipcr): View
    {
        return view('ipcrs.show', [
            'ipcr' => $ipcr->load(['upcr.dpcr.opcr.evaluationCycle', 'upcr.dpcr.opcr.office', 'employee.office', 'preparedBy', 'approvedBy']),
        ]);
    }

    public function approve(Request $request, Ipcr $ipcr): RedirectResponse
    {
        abort_unless($request->user()->hasAnyRole(['Administrator', 'Campus Director', 'Office Head', 'Supervisor']), 403);

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

        return redirect()
            ->route('ipcrs.show', $ipcr)
            ->with('status', 'IPCR approved successfully.');
    }
}
