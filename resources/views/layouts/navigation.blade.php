<nav x-data="{ open: false, accountOpen: false }" class="border-b border-gray-200 bg-white md:sticky md:top-0 md:flex md:h-screen md:w-72 md:shrink-0 md:flex-col md:border-b-0 md:border-r">
    @php
        $navigationUser = Auth::user()->loadMissing(['office', 'roles']);
        $officeRoleLabel = ($navigationUser->office?->name ?? 'No Office Assigned').' - '.($navigationUser->roles->pluck('name')->join(', ') ?: 'No Role Assigned');
    @endphp

    <div class="flex h-16 items-center justify-between px-4 md:h-auto md:px-5 md:py-5">
        <a href="{{ route('dashboard') }}" class="flex min-w-0 items-center gap-3">
            <img src="{{ asset('images/logoslsu.png') }}" alt="Southern Leyte State University logo" class="h-11 w-11 shrink-0 rounded-full object-contain">
            <span class="min-w-0">
                <span class="block truncate text-sm font-bold text-spms-ink">SLSU SPMS</span>
                <span class="block truncate text-xs font-medium text-gray-500">Performance Management</span>
            </span>
        </a>

        <button @click="open = ! open" class="inline-flex items-center justify-center rounded-md p-2 text-gray-500 transition duration-150 ease-in-out hover:bg-gray-100 hover:text-spms-ink focus:bg-gray-100 focus:outline-none md:hidden">
            <svg class="h-6 w-6" stroke="currentColor" fill="none" viewBox="0 0 24 24">
                <path :class="{'hidden': open, 'inline-flex': ! open }" class="inline-flex" stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 6h16M4 12h16M4 18h16" />
                <path :class="{'hidden': ! open, 'inline-flex': open }" class="hidden" stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12" />
            </svg>
        </button>
    </div>

    <div :class="{'block': open, 'hidden': ! open}" class="hidden border-t border-gray-100 md:block md:flex-1 md:border-t-0">
        <div class="flex h-full flex-col">
            <div class="flex-1 space-y-1 overflow-y-auto px-3 py-4">
                <p class="px-3 pb-2 text-xs font-bold uppercase tracking-wide text-gray-400">Menu</p>

                <x-responsive-nav-link :href="route('dashboard')" :active="request()->routeIs('dashboard')">
                    {{ $officeRoleLabel }}
                </x-responsive-nav-link>
                <x-responsive-nav-link :href="route('notifications.index')" :active="request()->routeIs('notifications.*')">
                    <span class="flex items-center justify-between gap-3">
                        <span>{{ __('Notifications') }}</span>
                        @if (Auth::user()->unreadNotifications()->count() > 0)
                            <span class="rounded-full bg-spms-red px-2 py-0.5 text-xs font-bold text-white">{{ Auth::user()->unreadNotifications()->count() }}</span>
                        @endif
                    </span>
                </x-responsive-nav-link>
                <x-responsive-nav-link :href="route('reports.index')" :active="request()->routeIs('reports.*')">
                    {{ __('Reports') }}
                </x-responsive-nav-link>

                @hasanyrole('Administrator|HR|HRM Office II')
                    <x-responsive-nav-link :href="route('offices.index')" :active="request()->routeIs('offices.*')">
                        {{ __('Offices') }}
                    </x-responsive-nav-link>
                    <x-responsive-nav-link :href="route('employees.index')" :active="request()->routeIs('employees.*')">
                        {{ __('Employees') }}
                    </x-responsive-nav-link>
                @endhasanyrole

                @hasanyrole('Administrator|HR')
                    <x-responsive-nav-link :href="route('evaluation-cycles.index')" :active="request()->routeIs('evaluation-cycles.*')">
                        {{ __('Cycles') }}
                    </x-responsive-nav-link>
                @endhasanyrole

                @hasanyrole('Administrator|HR|Campus Director|Office Head|HRM Office II')
                    <x-responsive-nav-link :href="route('opcrs.index')" :active="request()->routeIs('opcrs.*')">
                        {{ __('OPCR') }}
                    </x-responsive-nav-link>
                @endhasanyrole

                @hasanyrole('Administrator|HR|Campus Director|Office Head|Supervisor|HRM Office II')
                    <x-responsive-nav-link :href="route('dpcrs.index')" :active="request()->routeIs('dpcrs.*')">
                        {{ __('DPCR') }}
                    </x-responsive-nav-link>
                @endhasanyrole

                @hasanyrole('Administrator|HR|Campus Director|Office Head|Supervisor')
                    <x-responsive-nav-link :href="route('evaluations.index')" :active="request()->routeIs('evaluations.*')">
                        {{ __('Evaluations') }}
                    </x-responsive-nav-link>
                @endhasanyrole

                @hasanyrole('Administrator|HR|Campus Director|Office Head|Supervisor|Employee|HRM Office II')
                    <x-responsive-nav-link :href="route('upcrs.index')" :active="request()->routeIs('upcrs.*')">
                        {{ __('UPCR') }}
                    </x-responsive-nav-link>
                    <x-responsive-nav-link :href="route('ipcrs.index')" :active="request()->routeIs('ipcrs.*')">
                        {{ __('IPCR') }}
                    </x-responsive-nav-link>
                @endhasanyrole

                @hasanyrole('Administrator|HR|Campus Director|Office Head|Supervisor|Employee')
                    <x-responsive-nav-link :href="route('performance-targets.index')" :active="request()->routeIs('performance-targets.*')">
                        {{ __('Targets') }}
                    </x-responsive-nav-link>
                    <x-responsive-nav-link :href="route('accomplishments.index')" :active="request()->routeIs('accomplishments.*')">
                        {{ __('Accomplishments') }}
                    </x-responsive-nav-link>
                    <x-responsive-nav-link :href="route('movs.index')" :active="request()->routeIs('movs.*')">
                        {{ __('MOV') }}
                    </x-responsive-nav-link>
                @endhasanyrole
            </div>

            <div class="border-t border-gray-200 px-4 py-4">
                <button type="button" @click="accountOpen = ! accountOpen" class="flex w-full items-center justify-between gap-3 rounded-lg px-3 py-2 text-left transition hover:bg-gray-50 focus:bg-gray-50 focus:outline-none">
                    <span class="min-w-0">
                        <span class="block truncate text-sm font-semibold text-spms-ink">{{ Auth::user()->name }}</span>
                        <span class="block truncate text-xs text-gray-500">{{ Auth::user()->email }}</span>
                    </span>
                    <svg class="h-4 w-4 shrink-0 text-gray-500 transition" :class="{ 'rotate-180': accountOpen }" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 9l-7 7-7-7" />
                    </svg>
                </button>

                <div x-show="accountOpen" x-transition class="mt-2 space-y-1">
                    <x-responsive-nav-link :href="route('profile.edit')" :active="request()->routeIs('profile.edit')">
                        {{ __('Profile') }}
                    </x-responsive-nav-link>

                    <form method="POST" action="{{ route('logout') }}">
                        @csrf

                        <x-responsive-nav-link :href="route('logout')" :active="false"
                                onclick="event.preventDefault();
                                            this.closest('form').submit();">
                            {{ __('Log Out') }}
                        </x-responsive-nav-link>
                    </form>
                </div>
            </div>
        </div>
    </div>
</nav>
