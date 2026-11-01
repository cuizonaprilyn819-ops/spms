@php
    $errorCode = trim($__env->yieldContent('code'));
    $errorTitle = match ($errorCode) {
        '401' => 'Please sign in', '403' => 'Access unavailable', '404' => 'Page not found',
        '429' => 'Please wait before trying again', '500' => 'Something went wrong',
        '503' => 'Service temporarily unavailable', default => 'Unable to open this page',
    };
@endphp
<x-guest-layout>
    <div class="spms-recovery-message">
        <p class="text-xs font-semibold text-spms-navy">{{ $errorCode }}</p>
        <h1>{{ $errorTitle }}</h1>
        <p>{{ $__env->yieldContent('message') }}</p>
        <p>Return to your workspace or ask your administrator for help.</p>
        <a class="spms-button spms-button-primary inline-flex" href="{{ url('/') }}">Return to workspace</a>
    </div>
</x-guest-layout>
