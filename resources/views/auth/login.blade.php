<x-guest-layout>
    <h2 class="mb-5 text-center text-3xl font-semibold tracking-tight text-[#142b4d]">Sign in</h2>

    <x-auth-session-status class="mb-5 rounded-xl border border-emerald-200 bg-emerald-50 px-4 py-3" :status="session('status')" />

    <form method="POST" action="{{ route('login') }}" class="space-y-4">
        @csrf

        <div>
            <label for="email" class="mb-2 block text-sm font-semibold text-[#243a5a]">Email address</label>
            <input id="email" type="email" name="email" value="{{ old('email') }}" required autofocus autocomplete="username" placeholder="name@slsu.edu.ph" class="block w-full rounded-xl border-slate-200 px-4 py-2.5 text-sm text-spms-ink placeholder:text-slate-400 focus:border-spms-navy focus:ring-spms-navy">
            <x-input-error :messages="$errors->get('email')" class="mt-2" />
        </div>

        <div x-data="{ showPassword: false }">
            <div class="mb-2 flex items-center justify-between gap-3">
                <label for="password" class="text-sm font-semibold text-[#243a5a]">Password</label>
                @if (Route::has('password.request'))
                    <a href="{{ route('password.request') }}" class="rounded text-xs font-semibold text-spms-navy hover:underline focus:outline-none focus:ring-2 focus:ring-spms-sky focus:ring-offset-2">Forgot password?</a>
                @endif
            </div>
            <div class="relative">
                <input id="password" :type="showPassword ? 'text' : 'password'" type="password" name="password" required autocomplete="current-password" placeholder="Enter your password" class="block w-full rounded-xl border-slate-200 py-2.5 pl-4 pr-12 text-sm text-spms-ink placeholder:text-slate-400 focus:border-spms-navy focus:ring-spms-navy">
                <button type="button" @click="showPassword = !showPassword" :aria-label="showPassword ? 'Hide password' : 'Show password'" :aria-pressed="showPassword.toString()" class="absolute inset-y-0 right-0 flex w-12 items-center justify-center rounded-r-xl text-slate-500 hover:text-spms-navy focus:outline-none focus:ring-2 focus:ring-inset focus:ring-spms-navy">
                    <svg x-show="!showPassword" aria-hidden="true" class="h-5 w-5" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="1.8"><path stroke-linecap="round" stroke-linejoin="round" d="M2.25 12s3.5-6 9.75-6 9.75 6 9.75 6-3.5 6-9.75 6-9.75-6-9.75-6Z" /><circle cx="12" cy="12" r="3" /></svg>
                    <svg x-cloak x-show="showPassword" aria-hidden="true" class="h-5 w-5" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="1.8"><path stroke-linecap="round" stroke-linejoin="round" d="M3 3l18 18M10.6 6.1A10.5 10.5 0 0 1 12 6c6.25 0 9.75 6 9.75 6a17.2 17.2 0 0 1-3.2 3.7M6.2 6.8C3.6 8.5 2.25 12 2.25 12S5.75 18 12 18c1.4 0 2.7-.3 3.8-.8M9 9a4.2 4.2 0 0 0 6 6" /></svg>
                </button>
            </div>
            <x-input-error :messages="$errors->get('password')" class="mt-2" />
        </div>

        <label for="remember_me" class="inline-flex cursor-pointer items-center gap-2.5 text-sm text-slate-600">
            <input id="remember_me" type="checkbox" name="remember" class="rounded border-slate-300 text-spms-navy focus:ring-spms-sky">
            <span>Remember me</span>
        </label>

        <button type="submit" class="flex w-full items-center justify-center gap-2 rounded-xl bg-[#24477e] px-5 py-3 text-sm font-semibold text-white shadow-sm transition hover:bg-[#183a70] focus:outline-none focus:ring-2 focus:ring-spms-sky focus:ring-offset-2">
            Sign in
            <svg aria-hidden="true" class="h-4 w-4" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M5 12h14m-6-6 6 6-6 6" /></svg>
        </button>
    </form>

    <p class="mt-5 text-center text-xs leading-5 text-slate-500">Access is for authorized SLSU personnel. Contact your system administrator if you need an account.</p>
</x-guest-layout>
