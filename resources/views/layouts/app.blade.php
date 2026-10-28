<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
    <head>
        <meta charset="utf-8">
        <meta name="viewport" content="width=device-width, initial-scale=1">
        <meta name="csrf-token" content="{{ csrf_token() }}">
        <meta name="spms-base-url" content="{{ url('/') }}">

        <title>{{ ucwords(str_replace('-', ' ', explode('.', preg_replace('/^api\./', '', request()->route()->getName() ?? 'dashboard'))[0])) }} · SLSU SPMS</title>
        <link rel="icon" type="image/png" href="{{ asset('images/logoslsu.png') }}">

        <!-- Fonts -->
        <x-workspace-font />

        <!-- Scripts -->
        @vite(['resources/css/app.css', 'resources/js/app.js'])
    </head>
    <body class="font-sans antialiased">
        <a class="spms-skip-link" href="#main-content">Skip to content</a>
        <div class="spms-loading-bar" aria-hidden="true"></div>
        <div class="sr-only" role="status" aria-live="polite" data-navigation-status></div>
        <div class="spms-workspace min-h-screen md:flex">
            @include('layouts.navigation')

            <div class="min-w-0 flex-1">
                <header class="spms-topbar">
                    <a href="{{ route('dashboard') }}" class="spms-breadcrumb" aria-label="Back to dashboard">
                        <span>SLSU SPMS</span><span aria-hidden="true">›</span>
                        <strong>{{ ucwords(str_replace('-', ' ', explode('.', preg_replace('/^api\./', '', request()->route()->getName() ?? 'dashboard'))[0])) }}</strong>
                    </a>
                    <x-account-menu />
                </header>
                <!-- Page Heading -->
                @isset($header)
                    <header class="spms-page-heading">
                        <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8">
                            <div class="min-w-0 flex-1">{{ $header }}</div>
                        </div>
                    </header>
                @endisset

                <!-- Page Content -->
                <main id="main-content" tabindex="-1">
                    {{ $slot }}
                </main>
            </div>
        </div>
        @auth
            <x-ai-chat />
        @endauth
    </body>
</html>
