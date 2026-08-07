<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
    <head>
        <meta charset="utf-8">
        <meta name="viewport" content="width=device-width, initial-scale=1">

        <title>{{ config('app.name', 'SPMS') }}</title>
        <link rel="icon" type="image/png" href="{{ asset('images/logoslsu.png') }}">

        <link rel="preconnect" href="https://fonts.bunny.net">
        <link href="https://fonts.bunny.net/css?family=poppins:400,500,600,700&display=swap" rel="stylesheet" />

        @vite(['resources/css/app.css', 'resources/js/app.js'])
    </head>
    <body class="bg-spms-surface font-sans text-spms-ink antialiased">
        <main class="min-h-screen">
            <section class="mx-auto flex min-h-screen max-w-7xl flex-col px-5 py-6 sm:px-8 lg:px-10">
                <nav class="flex items-center justify-between">
                    <a href="/" class="inline-flex items-center gap-3">
                        <img src="{{ asset('images/logoslsu.png') }}" alt="Southern Leyte State University logo" class="h-12 w-12 rounded-full object-contain">
                        <span>
                            <span class="block text-sm font-bold">SLSU SPMS</span>
                            <span class="block text-xs text-gray-500">Strategic Performance Management</span>
                        </span>
                    </a>

                    @if (Route::has('login'))
                        <div class="flex items-center gap-3">
                            @auth
                                <a href="{{ url('/dashboard') }}" class="rounded-lg bg-spms-navy px-5 py-2.5 text-sm font-semibold text-white shadow-sm transition hover:bg-[#2b4276] focus:outline-none focus:ring-2 focus:ring-spms-sky focus:ring-offset-2">
                                    Open Dashboard
                                </a>
                            @else
                                <a href="{{ route('login') }}" class="rounded-lg bg-spms-navy px-5 py-2.5 text-sm font-semibold text-white shadow-sm transition hover:bg-[#2b4276] focus:outline-none focus:ring-2 focus:ring-spms-sky focus:ring-offset-2">
                                    Login
                                </a>
                            @endauth
                        </div>
                    @endif
                </nav>

                <div class="grid flex-1 items-center gap-10 py-12 lg:grid-cols-[1.05fr_0.95fr]">
                    <div>
                        <p class="text-sm font-semibold uppercase text-spms-sky">Automated SPMS</p>
                        <h1 class="mt-4 max-w-4xl text-4xl font-bold leading-tight text-spms-ink sm:text-5xl">Design and Development of an Automated Strategic Performance Management System</h1>
                        <p class="mt-5 max-w-2xl text-base leading-7 text-gray-600">A Laravel-based platform for AI-assisted formulations, secure user access, and real-time performance tracking for institutional planning and evaluation.</p>

                        <div class="mt-8 flex flex-col gap-3 sm:flex-row">
                            @auth
                                <a href="{{ url('/dashboard') }}" class="inline-flex items-center justify-center rounded-lg bg-spms-navy px-6 py-3 text-sm font-semibold text-white shadow-sm transition hover:bg-[#2b4276] focus:outline-none focus:ring-2 focus:ring-spms-sky focus:ring-offset-2">
                                    Go to Welcome Page
                                </a>
                            @else
                                <a href="{{ route('login') }}" class="inline-flex items-center justify-center rounded-lg bg-spms-navy px-6 py-3 text-sm font-semibold text-white shadow-sm transition hover:bg-[#2b4276] focus:outline-none focus:ring-2 focus:ring-spms-sky focus:ring-offset-2">
                                    Login to Continue
                                </a>
                            @endauth
                        </div>
                    </div>

                    <div class="rounded-lg border border-gray-200 bg-white p-6 shadow-sm">
                        <div class="grid grid-cols-2 gap-3 sm:grid-cols-3">
                            <div class="rounded-lg bg-[#5ABDEE] p-5 text-white">
                                <p class="text-xs font-semibold uppercase">Module</p>
                                <p class="mt-10 text-lg font-bold">Auth</p>
                            </div>
                            <div class="rounded-lg bg-[#34508E] p-5 text-white">
                                <p class="text-xs font-semibold uppercase">Access</p>
                                <p class="mt-10 text-lg font-bold">Secure</p>
                            </div>
                            <div class="rounded-lg bg-[#28C76F] p-5 text-white">
                                <p class="text-xs font-semibold uppercase">Status</p>
                                <p class="mt-10 text-lg font-bold">Ready</p>
                            </div>
                        </div>

                        <div class="mt-6 space-y-3">
                            <div class="flex items-center justify-between rounded-lg border border-gray-200 px-4 py-3">
                                <span class="text-sm font-semibold text-spms-ink">Login Page</span>
                                <span class="text-sm font-semibold text-spms-green">Built</span>
                            </div>
                            <div class="flex items-center justify-between rounded-lg border border-gray-200 px-4 py-3">
                                <span class="text-sm font-semibold text-spms-ink">Logout Flow</span>
                                <span class="text-sm font-semibold text-spms-green">Built</span>
                            </div>
                            <div class="flex items-center justify-between rounded-lg border border-gray-200 px-4 py-3">
                                <span class="text-sm font-semibold text-spms-ink">Welcome Page</span>
                                <span class="text-sm font-semibold text-spms-green">Built</span>
                            </div>
                        </div>
                    </div>
                </div>
            </section>
        </main>
    </body>
</html>
