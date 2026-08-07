<x-app-layout>
    @php
        $user = Auth::user()->loadMissing(['office', 'roles']);
        $officeRoleLabel = ($user->office?->name ?? 'No Office Assigned').' - '.($user->roles->pluck('name')->join(', ') ?: 'No Role Assigned');
    @endphp

    <x-slot name="header">
        <h2 class="text-xl font-bold leading-tight text-spms-ink">
            {{ $officeRoleLabel }}
        </h2>
    </x-slot>

    <div class="py-10">
        <div class="max-w-7xl mx-auto sm:px-6 lg:px-8">
            <div class="grid gap-6 px-4 sm:px-0 lg:grid-cols-[1.2fr_0.8fr]">
                <div class="rounded-lg border border-gray-200 bg-white p-6 shadow-sm sm:p-8">
                    <p class="text-sm font-semibold uppercase text-spms-green">Authenticated</p>
                    <h1 class="mt-2 text-3xl font-bold text-spms-ink">Welcome, {{ Auth::user()->name }}.</h1>
                    <p class="mt-2 text-sm font-semibold text-spms-navy">{{ $officeRoleLabel }}</p>
                    <p class="mt-3 max-w-2xl text-sm leading-6 text-gray-600">You are now logged in to the Strategic Performance Management System. This dashboard is ready for the next modules: office targets, success indicators, rating formulas, and real-time tracking.</p>

                    <div class="mt-8 grid gap-3 sm:grid-cols-3">
                        <div class="rounded-lg border border-gray-200 p-4">
                            <div class="h-2 w-12 rounded-full bg-spms-sky"></div>
                            <p class="mt-4 text-sm font-semibold text-spms-ink">Formulation</p>
                            <p class="mt-1 text-xs text-gray-500">AI-assisted target drafting</p>
                        </div>
                        <div class="rounded-lg border border-gray-200 p-4">
                            <div class="h-2 w-12 rounded-full bg-spms-green"></div>
                            <p class="mt-4 text-sm font-semibold text-spms-ink">Tracking</p>
                            <p class="mt-1 text-xs text-gray-500">Live performance status</p>
                        </div>
                        <div class="rounded-lg border border-gray-200 p-4">
                            <div class="h-2 w-12 rounded-full bg-spms-amber"></div>
                            <p class="mt-4 text-sm font-semibold text-spms-ink">Review</p>
                            <p class="mt-1 text-xs text-gray-500">Reports and evaluation</p>
                        </div>
                    </div>
                </div>

                <div class="rounded-lg border border-gray-200 bg-white p-6 shadow-sm">
                    <h2 class="text-lg font-bold text-spms-ink">Account</h2>
                    <p class="mt-2 text-sm text-gray-600">{{ Auth::user()->email }}</p>

                    <form method="POST" action="{{ route('logout') }}" class="mt-6">
                        @csrf
                        <button type="submit" class="inline-flex w-full items-center justify-center rounded-lg border border-spms-red px-5 py-3 text-sm font-semibold text-spms-red transition hover:bg-spms-red hover:text-white focus:outline-none focus:ring-2 focus:ring-spms-red focus:ring-offset-2">
                            {{ __('Log Out') }}
                        </button>
                    </form>
                </div>
            </div>
        </div>
    </div>
</x-app-layout>
