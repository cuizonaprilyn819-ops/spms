<x-app-layout>
    <x-slot name="header">
        <div>
            <h2 class="text-xl font-bold text-spms-ink">Reset user password</h2>
            <p class="mt-1 text-sm text-gray-500">{{ $user->name }} · {{ $user->email }}</p>
        </div>
    </x-slot>

    <div class="py-8">
        <div class="mx-auto max-w-2xl px-4 sm:px-6 lg:px-8">
            <div class="rounded-2xl border border-slate-200 bg-white p-6 shadow-sm sm:p-8">
                <p class="text-sm text-slate-600">Set a new password for this account. Share it with the user through your usual secure channel.</p>
                <form method="POST" action="{{ route('admin.password-resets.update', $user) }}" class="mt-6 space-y-5">
                    @csrf
                    @method('PUT')
                    <div>
                        <x-input-label for="admin_password" value="Your administrator password" />
                        <x-text-input id="admin_password" name="admin_password" type="password" class="mt-1 block w-full" required autocomplete="current-password" />
                        <x-input-error :messages="$errors->get('admin_password')" class="mt-2" />
                    </div>
                    <div>
                        <x-input-label for="password" value="New password" />
                        <x-text-input id="password" name="password" type="password" class="mt-1 block w-full" required autocomplete="new-password" />
                        <x-input-error :messages="$errors->get('password')" class="mt-2" />
                    </div>
                    <div>
                        <x-input-label for="password_confirmation" value="Confirm new password" />
                        <x-text-input id="password_confirmation" name="password_confirmation" type="password" class="mt-1 block w-full" required autocomplete="new-password" />
                    </div>
                    <div class="flex flex-wrap items-center gap-3">
                        <x-primary-button>Reset password</x-primary-button>
                        <a href="{{ route('employees.index') }}" class="text-sm font-semibold text-slate-600 hover:text-spms-navy">Cancel</a>
                    </div>
                </form>
            </div>
        </div>
    </div>
</x-app-layout>
