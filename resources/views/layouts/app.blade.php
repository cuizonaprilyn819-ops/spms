<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
    <head>
        <meta charset="utf-8">
        <meta name="viewport" content="width=device-width, initial-scale=1">
        <meta name="csrf-token" content="{{ csrf_token() }}">
        <meta name="spms-base-url" content="{{ url('/') }}">

        <title>{{ config('app.name', 'SPMS') }}</title>
        <link rel="icon" type="image/png" href="{{ asset('images/logoslsu.png') }}">

        <!-- Fonts -->
        <link rel="preconnect" href="https://fonts.bunny.net">
        <link href="https://fonts.bunny.net/css?family=poppins:400,500,600,700&display=swap" rel="stylesheet" />

        <!-- Scripts -->
        @vite(['resources/css/app.css', 'resources/js/app.js'])
    </head>
    <body class="font-sans antialiased">
        <div class="spms-workspace min-h-screen md:flex">
            @include('layouts.navigation')

            <div class="min-w-0 flex-1">
                <header class="spms-topbar">
                    <a href="{{ route('dashboard') }}" class="spms-breadcrumb">
                        <img src="{{ asset('images/logoslsu.png') }}" alt="" class="h-8 w-8 object-contain">
                        <span>SLSU SPMS</span><span aria-hidden="true">›</span>
                        <strong>{{ ucwords(str_replace('-', ' ', explode('.', request()->route()->getName() ?? 'dashboard')[0])) }}</strong>
                    </a>
                    <x-account-menu />
                </header>
                <div class="spms-workspace-bar">
                    <span>Your authorized workspace</span>
                    <span class="spms-workspace-office">{{ Auth::user()->office?->name ?? 'Southern Leyte State University' }}</span>
                </div>
                <!-- Page Heading -->
                @isset($header)
                    <header class="spms-page-heading">
                        <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8">
                            <div class="min-w-0 flex-1">{{ $header }}</div>
                        </div>
                    </header>
                @endisset

                <!-- Page Content -->
                <main>
                    {{ $slot }}
                </main>
            </div>
        </div>
        @auth
            <x-ai-chat />
        @endauth
    </body>
</html>
