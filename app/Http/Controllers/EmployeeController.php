<?php

namespace App\Http\Controllers;

use App\Models\Office;
use App\Models\User;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Rules\Password;
use Illuminate\View\View;
use Spatie\Permission\Models\Role;

class EmployeeController extends Controller
{
    public function index(): View
    {
        $employees = User::with(['office', 'roles'])
            ->latest()
            ->paginate(10);

        return view('employees.index', compact('employees'));
    }

    public function create(): View
    {
        return view('employees.create', $this->formData());
    }

    public function store(Request $request): RedirectResponse
    {
        $data = $this->validatedData($request);
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
            ...$this->formData(),
            'employee' => $employee->load(['office', 'roles']),
        ]);
    }

    public function update(Request $request, User $employee): RedirectResponse
    {
        $data = $this->validatedData($request, $employee);
        $role = $data['role'];
        unset($data['role']);

        if (blank($data['password'] ?? null)) {
            unset($data['password']);
        }

        $employee->update($data);
        $employee->syncRoles([$role]);

        return redirect()
            ->route('employees.index')
            ->with('status', 'Employee updated successfully.');
    }

    public function destroy(User $employee): RedirectResponse
    {
        if ($employee->is(auth()->user())) {
            return redirect()
                ->route('employees.index')
                ->with('status', 'You cannot delete your own account while logged in.');
        }

        $employee->delete();

        return redirect()
            ->route('employees.index')
            ->with('status', 'Employee deleted successfully.');
    }

    /**
     * @return array{offices: \Illuminate\Database\Eloquent\Collection<int, Office>, roles: \Illuminate\Database\Eloquent\Collection<int, Role>}
     */
    private function formData(): array
    {
        $roles = Role::orderBy('name');

        if (! auth()->user()?->hasAnyRole(['Administrator', 'HR'])) {
            $roles->where('name', 'Employee');
        }

        return [
            'offices' => Office::orderBy('name')->get(),
            'roles' => $roles->get(),
        ];
    }

    /**
     * @return array{name: string, email: string, office_id?: int|null, role: string, password?: string|null}
     */
    private function validatedData(Request $request, ?User $employee = null): array
    {
        $allowedRoles = auth()->user()?->hasAnyRole(['Administrator', 'HR'])
            ? Role::query()->pluck('name')->all()
            : ['Employee'];

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
            'office_id' => ['nullable', 'integer', Rule::exists('offices', 'id')],
            'role' => ['required', 'string', Rule::in($allowedRoles)],
            'password' => [
                $employee ? 'nullable' : 'required',
                'confirmed',
                Password::defaults(),
            ],
        ]);
    }
}
