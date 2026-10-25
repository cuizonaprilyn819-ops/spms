<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <title>{{ config('app.name', 'SLSU SPMS') }}</title>
    <link rel="icon" type="image/png" href="{{ asset('images/logoslsu.png') }}">
    <link rel="preconnect" href="https://fonts.bunny.net">
    <link href="https://fonts.bunny.net/css?family=poppins:400,500,600,700&display=swap" rel="stylesheet" />
    @vite(['resources/css/app.css', 'resources/js/app.js'])
</head>
<body class="spms-guest font-sans text-spms-ink antialiased">
    <main class="flex min-h-screen items-center justify-center px-4 py-8 sm:px-6 sm:py-12">
        <div class="w-full max-w-[480px] rounded-3xl bg-white px-6 py-6 shadow-[0_28px_80px_-25px_rgba(4,18,43,0.5)] sm:px-10 sm:py-7">
            <div class="mb-6 flex flex-col items-center gap-2 border-b border-slate-100 pb-5 text-center">
                <img src="{{ asset('images/logoslsu.png') }}" alt="Southern Leyte State University seal" class="h-20 w-20 rounded-full object-contain">
                <div>
                    <p class="text-sm font-semibold text-[#142b4d]">SLSU SPMS</p>
                    <p class="text-xs text-slate-500">Southern Leyte State University</p>
                </div>
            </div>

            {{ $slot }}

            <p class="mt-5 text-center text-xs text-slate-400">&copy; {{ date('Y') }} Southern Leyte State University</p>
        </div>
    </main>
</body>
</html>
