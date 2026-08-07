<?php

namespace App\Http\Controllers;

use App\Models\Office;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Illuminate\View\View;

class OfficeController extends Controller
{
    public function index(): View
    {
        $offices = Office::withCount('employees')
            ->latest()
            ->paginate(10);

        return view('offices.index', compact('offices'));
    }

    public function create(): View
    {
        return view('offices.create');
    }

    public function store(Request $request): RedirectResponse
    {
        Office::create($this->validatedData($request));

        return redirect()
            ->route('offices.index')
            ->with('status', 'Office added successfully.');
    }

    public function edit(Office $office): View
    {
        return view('offices.edit', compact('office'));
    }

    public function update(Request $request, Office $office): RedirectResponse
    {
        $office->update($this->validatedData($request, $office));

        return redirect()
            ->route('offices.index')
            ->with('status', 'Office updated successfully.');
    }

    public function destroy(Office $office): RedirectResponse
    {
        $office->delete();

        return redirect()
            ->route('offices.index')
            ->with('status', 'Office deleted successfully.');
    }

    /**
     * @return array{name: string, code?: string|null, description?: string|null}
     */
    private function validatedData(Request $request, ?Office $office = null): array
    {
        return $request->validate([
            'name' => [
                'required',
                'string',
                'max:255',
                Rule::unique('offices', 'name')->ignore($office),
            ],
            'code' => [
                'nullable',
                'string',
                'max:50',
                Rule::unique('offices', 'code')->ignore($office),
            ],
            'description' => ['nullable', 'string', 'max:1000'],
        ]);
    }
}
