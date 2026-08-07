<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
    <head>
        <meta charset="utf-8">
        <meta name="viewport" content="width=device-width, initial-scale=1">
        <meta name="csrf-token" content="{{ csrf_token() }}">

        <title>{{ config('app.name', 'SPMS') }}</title>
        <link rel="icon" type="image/png" href="{{ asset('images/logoslsu.png') }}">

        <!-- Fonts -->
        <link rel="preconnect" href="https://fonts.bunny.net">
        <link href="https://fonts.bunny.net/css?family=poppins:400,500,600,700&display=swap" rel="stylesheet" />

        <!-- Scripts -->
        @vite(['resources/css/app.css', 'resources/js/app.js'])
    </head>
    <body class="font-sans text-spms-ink antialiased">
        <div class="min-h-screen bg-spms-surface">
            <div class="grid min-h-screen lg:grid-cols-[1.05fr_0.95fr]">
                <section class="hidden bg-spms-navy px-12 py-10 text-white lg:flex lg:flex-col lg:justify-between">
                    <a href="/" class="inline-flex items-center gap-3">
                        <img src="{{ asset('images/logoslsu.png') }}" alt="Southern Leyte State University logo" class="h-14 w-14 rounded-full bg-white object-contain p-1">
                        <span>
                            <span class="block text-sm font-semibold">SLSU</span>
                            <span class="block text-xs text-white/75">Strategic Performance Management</span>
                        </span>
                    </a>

                    <div class="max-w-xl">
                        <p class="mb-4 text-sm font-semibold uppercase text-spms-sky">Automated SPMS</p>
                        <h1 class="text-4xl font-bold leading-tight">AI-assisted formulation and real-time tracking for strategic performance.</h1>
                        <div class="mt-8 grid grid-cols-3 gap-3">
                            <div class="h-2 rounded-full bg-spms-sky"></div>
                            <div class="h-2 rounded-full bg-spms-green"></div>
                            <div class="h-2 rounded-full bg-spms-amber"></div>
                        </div>
                    </div>

                    <p class="max-w-lg text-sm leading-6 text-white/70">Secure access for administrators, offices, and evaluators preparing performance commitments, tracking outputs, and reviewing progress.</p>
                </section>

                <main class="flex min-h-screen items-center justify-center px-5 py-10">
                    <div class="w-full max-w-md">
                        <div class="mb-8 lg:hidden">
                            <a href="/" class="inline-flex items-center gap-3">
                                <img src="{{ asset('images/logoslsu.png') }}" alt="Southern Leyte State University logo" class="h-12 w-12 rounded-full bg-white object-contain p-1 shadow-sm">
                                <span>
                                    <span class="block text-sm font-semibold text-spms-ink">SLSU SPMS</span>
                                    <span class="block text-xs text-gray-500">Strategic Performance Management</span>
                                </span>
                            </a>
                        </div>

                        <div class="rounded-lg border border-gray-200 bg-white p-6 shadow-sm sm:p-8">
                {{ $slot }}
                        </div>
                    </div>
                </main>
            </div>
        </div>
    </body>
</html>
