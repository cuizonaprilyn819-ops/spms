<form
    method="POST"
    action="{{ $action }}"
    class="space-y-6"
    x-data="{ selectedRole: {{ Illuminate\Support\Js::from(old('role', isset($employee) ? $employee->roles->first()?->name : '')) }} }"
>
    @csrf
    @if ($method !== 'POST')
        @method($method)
    @endif

    <div class="grid gap-6 sm:grid-cols-2">
        <div>
            <x-input-label for="name" :value="__('Full Name')" />
            <x-text-input id="name" name="name" type="text" class="mt-1 block w-full" :value="old('name', $employee?->name)" required autofocus />
            <x-input-error :messages="$errors->get('name')" class="mt-2" />
        </div>

        <div>
            <x-input-label for="email" :value="__('Email')" />
            <x-text-input id="email" name="email" type="email" class="mt-1 block w-full" :value="old('email', $employee?->email)" required />
            <x-input-error :messages="$errors->get('email')" class="mt-2" />
        </div>
    </div>

    <div class="grid gap-6 sm:grid-cols-2">
        <div>
            <x-input-label for="office_id" :value="__('Office')" />
            <x-searchable-select
                id="office_id"
                name="office_id"
                :options="$offices->map(fn ($office) => ['value' => $office->id, 'label' => $office->assignment_label.($office->is_active ? '' : ' (Inactive — current assignment)')])->values()->all()"
                :selected="old('office_id', $employee?->office_id)"
                placeholder="Search offices..."
            />
            <x-input-error :messages="$errors->get('office_id')" class="mt-2" />
        </div>

        <div>
            <x-input-label for="role" :value="__('Role')" />
            <select id="role" name="role" x-model="selectedRole" class="mt-1 block w-full rounded-lg border-gray-300 shadow-sm focus:border-spms-sky focus:ring-spms-sky" required>
                <option value="">Select role</option>
                @foreach ($roles as $role)
                    <option value="{{ $role->name }}" @selected(old('role', isset($employee) ? $employee->roles->first()?->name : null) === $role->name)>
                        {{ $role->name }}
                    </option>
                @endforeach
            </select>
            <x-input-error :messages="$errors->get('role')" class="mt-2" />
        </div>
    </div>

    <div>
        <x-input-label for="position_title_id" :value="__('CSC Position Title')" />
        <x-searchable-select
            id="position_title_id"
            name="position_title_id"
            :options="$positionTitles->map(fn ($positionTitle) => [
                'value' => $positionTitle->id,
                'label' => $positionTitle->title.($positionTitle->salary_grade && $positionTitle->salary_grade !== '0' ? ' — SG '.$positionTitle->salary_grade : ''),
            ])->values()->all()"
            :selected="old('position_title_id', $employee?->position_title_id)"
            placeholder="Search CSC position titles..."
        />
        <x-input-error :messages="$errors->get('position_title_id')" class="mt-2" />
    </div>

    @unless ($employee)
    <div class="grid gap-6 sm:grid-cols-2">
        <div>
            <x-input-label for="password" :value="__('Initial Password')" />
            <x-text-input id="password" name="password" type="password" class="mt-1 block w-full" required autocomplete="new-password" />
            <x-input-error :messages="$errors->get('password')" class="mt-2" />
        </div>

        <div>
            <x-input-label for="password_confirmation" :value="__('Confirm Password')" />
            <x-text-input id="password_confirmation" name="password_confirmation" type="password" class="mt-1 block w-full" required autocomplete="new-password" />
        </div>
    </div>
    @endunless

    <div class="flex items-center justify-end gap-3">
        <a href="{{ route('employees.index') }}" class="rounded-lg border border-gray-300 px-5 py-3 text-sm font-semibold text-spms-ink transition hover:bg-gray-50">Cancel</a>
        <x-primary-button>{{ $submit }}</x-primary-button>
    </div>
</form>
