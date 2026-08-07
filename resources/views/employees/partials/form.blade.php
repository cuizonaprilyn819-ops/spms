<form method="POST" action="{{ $action }}" class="space-y-6">
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
            <select id="office_id" name="office_id" class="mt-1 block w-full rounded-lg border-gray-300 shadow-sm focus:border-spms-sky focus:ring-spms-sky">
                <option value="">Unassigned</option>
                @foreach ($offices as $office)
                    <option value="{{ $office->id }}" @selected((string) old('office_id', $employee?->office_id) === (string) $office->id)>
                        {{ $office->name }}
                    </option>
                @endforeach
            </select>
            <x-input-error :messages="$errors->get('office_id')" class="mt-2" />
        </div>

        <div>
            <x-input-label for="role" :value="__('Role')" />
            <select id="role" name="role" class="mt-1 block w-full rounded-lg border-gray-300 shadow-sm focus:border-spms-sky focus:ring-spms-sky" required>
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

    <div class="grid gap-6 sm:grid-cols-2">
        <div>
            <x-input-label for="password" :value="$employee ? __('New Password') : __('Password')" />
            <x-text-input id="password" name="password" type="password" class="mt-1 block w-full" :required="! $employee" autocomplete="new-password" />
            @if ($employee)
                <p class="mt-1 text-xs text-gray-500">Leave blank to keep the current password.</p>
            @endif
            <x-input-error :messages="$errors->get('password')" class="mt-2" />
        </div>

        <div>
            <x-input-label for="password_confirmation" :value="__('Confirm Password')" />
            <x-text-input id="password_confirmation" name="password_confirmation" type="password" class="mt-1 block w-full" :required="! $employee" autocomplete="new-password" />
        </div>
    </div>

    <div class="flex items-center justify-end gap-3">
        <a href="{{ route('employees.index') }}" class="rounded-lg border border-gray-300 px-5 py-3 text-sm font-semibold text-spms-ink transition hover:bg-gray-50">Cancel</a>
        <x-primary-button>{{ $submit }}</x-primary-button>
    </div>
</form>
