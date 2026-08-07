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
        $dpcrs = Dpcr::with(['opcr.evaluationCycle', 'opcr.office', 'employee', 'preparedBy', 'approvedBy'])
            ->latest()
            ->paginate(10);

        return view('dpcrs.index', compact('dpcrs'));
    }

    public function create(): View
    {
        return view('dpcrs.create', [
            'opcrs' => Opcr::with(['evaluationCycle', 'office'])
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
            'opcr_id' => ['required', 'integer', Rule::exists('opcrs', 'id')],
            'employee_id' => ['required', 'integer', Rule::exists('users', 'id')],
            'title' => ['required', 'string', 'max:255'],
            'success_indicators' => ['nullable', 'string', 'max:5000'],
            'targets' => ['nullable', 'string', 'max:5000'],
        ]);

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
        return view('dpcrs.show', [
            'dpcr' => $dpcr->load(['opcr.evaluationCycle', 'opcr.office', 'employee.office', 'preparedBy', 'approvedBy']),
        ]);
    }

    public function approve(Request $request, Dpcr $dpcr): RedirectResponse
    {
        abort_unless($request->user()->hasAnyRole(['Administrator', 'Campus Director', 'Office Head']), 403);

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
}
