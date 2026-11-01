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

            <div class="spms-content min-w-0 flex-1">
                <header class="spms-topbar">
                    <div class="spms-university"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.6" aria-hidden="true"><path d="M3 21h18M5 21V3h14v18M9 7h1M14 7h1M9 11h1M14 11h1M10 21v-5h4v5"/></svg><span>Southern Leyte State University</span></div>
                    <div class="spms-topbar-tools"><x-period-reference /><x-account-menu /></div>
                </header>
                <div class="spms-page-trail"><x-workspace-breadcrumb /></div>
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
                <footer class="spms-workspace-footer"><span>SLSU SPMS</span><span>Institution-wide performance management</span></footer>
            </div>
        </div>
        @auth
            <x-ai-chat />
        @endauth
    </body>
</html>
