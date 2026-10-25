<x-guest-layout>
    <h1 class="text-lg font-semibold text-spms-ink">Confirm logout</h1>
    <p class="mt-3 text-sm text-gray-600">The previous page expired. You are currently signed in as {{ Auth::user()->name }}. Click below to log out.</p>
    <form method="POST" action="{{ route('logout') }}" class="mt-5">
        @csrf
        <x-primary-button>Log Out</x-primary-button>
        <a href="{{ route('dashboard') }}" class="ml-3 text-sm underline">Return to dashboard</a>
    </form>
</x-guest-layout>
