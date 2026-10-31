<?php

namespace App\Http\Controllers;

use App\Models\Office;
use App\Models\PositionTitle;
use App\Models\User;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Rules\Password;
use Illuminate\View\View;
use Spatie\Permission\Models\Role;

class EmployeeController extends Controller
{
    private const ASSIGNABLE_ROLES = ['Planning Officer', 'Supervisor', 'Employee'];

    public function index(Request $request): View
    {
        $filters = $request->validate([
            'search' => ['nullable', 'string', 'max:200'],
            'office_id' => ['nullable', 'integer', 'exists:offices,id'],
        ]);
        $employees = User::with(['office', 'positionTitle', 'roles'])
            ->when($filters['search'] ?? null, function ($query, $search) {
                $query->where(function ($query) use ($search) {
                    $query->whereLike('name', '%'.$search.'%')
                        ->orWhereLike('email', '%'.$search.'%');
                });
            })
            ->when($filters['office_id'] ?? null, fn ($query, $office) => $query->where('office_id', $office))
            ->latest()
            ->paginate(10)
            ->withQueryString();
        $offices = Office::orderBy('name')->get();

        return view('employees.index', compact('employees', 'offices'));
    }

    public function create(): View
    {
        return view('employees.create', $this->formData());
    }

    public function store(Request $request): RedirectResponse
    {
        $data = $this->validatedData($request);
        $this->validatePositionRole($request, $data);
        $role = $data['role'];
        unset($data['role']);

        $employee = User::create($data);
        $employee->syncRoles([$role]);

        return redirect()
            ->route('employees.index')
            ->with('status', 'Employee added successfully.');
    }

    public function edit(User $employee): View
    {
        return view('employees.edit', [
            ...$this->formData($employee),
            'employee' => $employee->load(['office', 'roles']),
        ]);
    }

    public function update(Request $request, User $employee): RedirectResponse
    {
        abort_if($request->filled('password') || $request->filled('password_confirmation'), 403);

        $data = $this->validatedData($request, $employee);
        $this->validatePositionRole($request, $data);
        $role = $data['role'];
        unset($data['role']);

        $employee->update($data);
        $employee->syncRoles([$role]);

        return redirect()
            ->route('employees.index')
            ->with('status', 'Employee updated successfully.');
    }

    public function destroy(User $employee): RedirectResponse
    {
        if ($employee->hasRole('Administrator')) {
            return redirect()
                ->route('employees.index')
                ->with('status', 'Administrator accounts cannot be deleted.');
        }

        if ($employee->is(auth()->user())) {
            return redirect()
                ->route('employees.index')
                ->with('status', 'You cannot delete your own account while logged in.');
        }

        if ($employee->hasRetainedArchiveRecords()) {
            return redirect()->route('employees.index')->with('status', 'This account is linked to retained PCR history or a sign-in identity and cannot be deleted.');
        }

        $employee->delete();

        return redirect()
            ->route('employees.index')
            ->with('status', 'Employee deleted successfully.');
    }

    /**
     * @return array{offices: \Illuminate\Database\Eloquent\Collection<int, Office>, positionTitles: \Illuminate\Database\Eloquent\Collection<int, PositionTitle>, roles: \Illuminate\Database\Eloquent\Collection<int, Role>}
     */
    private function formData(?User $employee = null): array
    {
        $assignableRoles = $this->assignableRoles($employee);

        return [
            'offices' => Office::query()
                ->where(function ($query) use ($employee) {
                    $query->where(fn ($active) => $active->where('is_active', true)->where('campus', config('hinunangan.campus_id')));
                    if ($employee?->office_id) {
                        $query->orWhere('id', $employee->office_id);
                    }
                })->orderBy('name')->get(),
            'positionTitles' => PositionTitle::query()->where('is_active', true)->orderBy('title')->get(),
            'roles' => Role::query()
                ->whereIn('name', $assignableRoles)
                ->orderByRaw("CASE name WHEN 'Campus Director' THEN 0 WHEN 'HR Head' THEN 1 WHEN 'Planning Officer' THEN 2 WHEN 'Supervisor' THEN 3 ELSE 4 END")
                ->get(),
        ];
    }

    /**
     * @return array{name: string, email: string, office_id?: int|null, position_title_id?: int|null, role: string, password?: string|null}
     */
    private function validatedData(Request $request, ?User $employee = null): array
    {
        return $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'email' => [
                'required',
                'string',
                'lowercase',
                'email',
                'max:255',
                Rule::unique('users', 'email')->ignore($employee),
            ],
            'office_id' => ['nullable', 'integer', Rule::exists('offices', 'id')->where(function ($query) use ($employee) {
                $query->where(fn ($active) => $active->where('is_active', true)->where('campus', config('hinunangan.campus_id')));
                if ($employee?->office_id) {
                    $query->orWhere('id', $employee->office_id);
                }
            })],
            'position_title_id' => [
                'nullable',
                'integer',
                Rule::exists('position_titles', 'id')->where('is_active', true),
            ],
            'role' => ['required', 'string', Rule::in($this->assignableRoles($employee))],
            ...($employee ? [] : ['password' => ['required', 'confirmed', Password::defaults()]]),
        ]);
    }

    /**
     * @return list<string>
     */
    private function assignableRoles(?User $employee = null): array
    {
        $roles = self::ASSIGNABLE_ROLES;

        if (auth()->user()?->hasRole('Administrator')) {
            $anotherCampusDirectorExists = User::role('Campus Director')
                ->when($employee, fn ($query) => $query->where('users.id', '!=', $employee->id))
                ->exists();

            if (! $anotherCampusDirectorExists) {
                array_unshift($roles, 'Campus Director');
            }

            $anotherHrHeadExists = User::role('HR Head')
                ->when($employee, fn ($query) => $query->where('users.id', '!=', $employee->id))
                ->exists();
            if (! $anotherHrHeadExists) array_unshift($roles, 'HR Head');
        }

        return $roles;
    }

    private function validatePositionRole(Request $request, array $data): void
    {
        $position = isset($data['position_title_id']) ? PositionTitle::find($data['position_title_id']) : null;
        if ($position && in_array(strtolower($position->title), ['office head', 'department head'], true) && $data['role'] !== 'Supervisor') {
            throw \Illuminate\Validation\ValidationException::withMessages(['role' => 'Office Heads and Department Heads must have the Supervisor role.']);
        }
    }
}
