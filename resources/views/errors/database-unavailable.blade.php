<x-guest-layout>
    <div class="spms-recovery-message">
        <h1>SPMS temporarily unavailable</h1>
        <p>{{ $message }}</p>
        <p>Your login could not be completed while the database is unavailable.</p>
        <a class="spms-button spms-button-primary inline-flex" href="{{ url('/login') }}">Return to sign in</a>
    </div>
</x-guest-layout>
