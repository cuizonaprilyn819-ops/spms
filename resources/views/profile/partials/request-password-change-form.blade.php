<section>
    <h2 class="text-lg font-medium text-gray-900">Password change</h2>
    <p class="mt-1 text-sm text-gray-600">Request a password change from an administrator, or use Forgot password to reset it through your email.</p>

    @if (session('status') === 'password-change-requested')
        <p class="mt-5 rounded-lg border border-green-200 bg-green-50 px-4 py-3 text-sm font-medium text-green-700">Your request was sent to the administrators.</p>
    @endif
    <x-input-error :messages="$errors->get('passwordRequest')" class="mt-4" />

    <div class="mt-6 flex flex-wrap gap-3">
        <form method="POST" action="{{ route('password.change-request') }}">
            @csrf
            <x-primary-button>Request password change</x-primary-button>
        </form>
        <a href="{{ route('password.request') }}" class="inline-flex items-center rounded-lg border border-gray-300 px-5 py-3 text-sm font-semibold text-spms-ink hover:bg-gray-50">Forgot password</a>
    </div>
</section>
