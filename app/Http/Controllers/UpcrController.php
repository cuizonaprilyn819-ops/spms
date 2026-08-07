<?php

namespace App\Http\Controllers;

use App\Models\Dpcr;
use App\Models\Upcr;
use App\Models\User;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Illuminate\View\View;

class UpcrController extends Controller
{
    public function index(): View
    {
        $upcrs = Upcr::with(['dpcr.opcr.evaluationCycle', 'dpcr.opcr.office', 'dpcr.employee', 'employee', 'preparedBy'])
            ->latest()
            ->paginate(10);

        return view('upcrs.index', compact('upcrs'));
    }

    public function create(): View
    {
        return view('upcrs.create', [
            'dpcrs' => Dpcr::with(['opcr.evaluationCycle', 'opcr.office', 'employee'])
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
            'dpcr_id' => ['required', 'integer', Rule::exists('dpcrs', 'id')],
            'employee_id' => ['required', 'integer', Rule::exists('users', 'id')],
            'title' => ['required', 'string', 'max:255'],
            'success_indicators' => ['nullable', 'string', 'max:5000'],
            'targets' => ['nullable', 'string', 'max:5000'],
        ]);

        $request->validate([
            'employee_id' => [
                Rule::unique('upcrs', 'employee_id')
                    ->where('dpcr_id', $request->integer('dpcr_id')),
            ],
        ]);

        $upcr = Upcr::create([
            ...$data,
            'prepared_by' => $request->user()->id,
        ]);

        return redirect()
            ->route('upcrs.show', $upcr)
            ->with('status', 'UPCR created successfully.');
    }

    public function show(Upcr $upcr): View
    {
        return view('upcrs.show', [
            'upcr' => $upcr->load(['dpcr.opcr.evaluationCycle', 'dpcr.opcr.office', 'dpcr.employee', 'employee.office', 'preparedBy']),
        ]);
    }
}
