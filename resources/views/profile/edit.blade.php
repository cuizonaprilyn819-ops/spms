<x-app-layout>
    <x-slot name="header">
        <h2 class="font-semibold text-xl text-gray-800 leading-tight">
            {{ __('Profile') }}
        </h2>
    </x-slot>

    <div class="py-12">
        <div class="max-w-7xl mx-auto sm:px-6 lg:px-8 space-y-6">
            <nav class="spms-page-tabs" data-workspace-tabs aria-label="Profile sections"><a href="#profile-account">Account</a><a href="#profile-security">Password &amp; security</a>@unless($user->hasRole('Administrator'))<a href="#profile-retention">Account retention</a>@endunless</nav>
            <div id="profile-account" class="p-4 sm:p-8 bg-white shadow sm:rounded-lg">
                <div class="max-w-xl">
                    @include('profile.partials.update-profile-information-form')
                </div>
            </div>

            <div id="profile-security" class="p-4 sm:p-8 bg-white shadow sm:rounded-lg">
                <div class="max-w-xl">
                    @if ($user->hasRole('Administrator'))
                        @include('profile.partials.update-password-form')
                    @else
                        @include('profile.partials.request-password-change-form')
                    @endif
                </div>
            </div>

            @unless ($user->hasRole('Administrator'))
                <div id="profile-retention" class="p-4 sm:p-8 bg-white shadow sm:rounded-lg">
                    <div class="max-w-xl">
                        @include('profile.partials.delete-user-form')
                    </div>
                </div>
            @endunless
        </div>
    </div>
</x-app-layout>
