<nav x-data="{ open: false, accountOpen: false }" class="spms-sidebar text-white md:sticky md:top-0 md:flex md:h-screen md:w-72 md:shrink-0 md:flex-col md:overflow-hidden">
    @php
        $routeIs = fn (...$patterns) => request()->routeIs(...[...$patterns, ...array_map(fn ($pattern) => 'api.'.$pattern, $patterns)]);
        $navigationUser = Auth::user()->loadMissing(['office', 'roles']);
        $officeRoleLabel = ($navigationUser->office?->name ?? 'No Office Assigned').' - '.($navigationUser->roles->pluck('name')->join(', ') ?: 'No Role Assigned');
    @endphp

    <div class="spms-brand flex items-center justify-between border-b border-white/15 px-5 py-6 md:px-7 md:py-9">
        <a href="{{ route('dashboard') }}" class="flex min-w-0 items-center gap-3">
            <img src="{{ asset('images/logoslsu.png') }}" alt="Southern Leyte State University logo" class="h-11 w-11 shrink-0 rounded-full object-contain">
            <span class="min-w-0">
                <span class="block truncate text-2xl font-bold tracking-tight text-white">SLSU SPMS</span>
                <span class="block truncate text-xs font-medium text-white/65">Performance Management</span>
            </span>
        </a>

        <button @click="open = ! open" class="inline-flex items-center justify-center rounded-md p-2 text-white/75 transition duration-150 ease-in-out hover:bg-white/10 hover:text-white focus:bg-white/10 focus:outline-none md:hidden" aria-label="Toggle navigation menu">
            <svg class="h-6 w-6" stroke="currentColor" fill="none" viewBox="0 0 24 24">
                <path :class="{'hidden': open, 'inline-flex': ! open }" class="inline-flex" stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 6h16M4 12h16M4 18h16" />
                <path :class="{'hidden': ! open, 'inline-flex': open }" class="hidden" stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12" />
            </svg>
        </button>
    </div>

    <div :class="{'block': open, 'hidden': ! open}" class="hidden border-t border-white/15 md:min-h-0 md:flex-1 md:border-t-0 md:block">
        <div class="flex h-full flex-col">
            <div class="spms-sidebar-scroll min-h-0 flex-1 space-y-1 overflow-y-auto px-3 py-4">
                <p class="px-3 pb-2 text-[11px] font-bold uppercase tracking-[0.16em] text-white/50">Workspace</p>

                <x-responsive-nav-link :href="route('dashboard')" :active="$routeIs('dashboard')">
                    {{ __('Dashboard') }}
                </x-responsive-nav-link>
                <x-responsive-nav-link :href="route('notifications.index')" :active="$routeIs('notifications.*')">
                    <span class="flex items-center justify-between gap-3">
                        <span>{{ __('Notifications') }}</span>
                        @if ($unreadCount > 0)
                            <span class="rounded-full bg-spms-red px-2 py-0.5 text-xs font-bold text-white">{{ $unreadCount }}</span>
                        @endif
                    </span>
                </x-responsive-nav-link>
                <x-responsive-nav-link :href="route('reports.index')" :active="$routeIs('reports.*') || $routeIs('opcrs.*') || $routeIs('dpcrs.*') || $routeIs('upcrs.*') || $routeIs('ipcrs.*')">
                    {{ __('My Reports') }}
                </x-responsive-nav-link>

                @hasanyrole('Administrator|HR|HR Head|HRM Office II')
                    <x-responsive-nav-link :href="route('offices.index')" :active="$routeIs('offices.*')">
                        {{ __('Offices') }}
                    </x-responsive-nav-link>
                    <x-responsive-nav-link :href="route('employees.index')" :active="$routeIs('employees.*')">
                        {{ __('Employees') }}
                    </x-responsive-nav-link>
                @endhasanyrole

                @hasanyrole('Administrator|HR|HR Head')
                    <x-responsive-nav-link :href="route('evaluation-cycles.index')" :active="$routeIs('evaluation-cycles.*')">
                        {{ __('Evaluation Periods') }}
                    </x-responsive-nav-link>
                @endhasanyrole

                @hasanyrole('Administrator|Campus Director')
                    <x-responsive-nav-link :href="route('target-documents.index')" :active="$routeIs('target-documents.*') || $routeIs('target-drafts.*')">
                        {{ __('Target Planning Documents') }}
                    </x-responsive-nav-link>
                @endhasanyrole

                @hasanyrole('Administrator|HR|HR Head|Campus Director|Supervisor')
                    <x-responsive-nav-link :href="route('evaluations.index')" :active="$routeIs('evaluations.*')">
                        {{ __('Evaluations') }}
                    </x-responsive-nav-link>
                @endhasanyrole

                @hasanyrole('HR|HR Head')
                    <x-responsive-nav-link :href="route('ratings.index')" :active="$routeIs('ratings.*')">
                        {{ __('All Ratings') }}
                    </x-responsive-nav-link>
                @endhasanyrole

                @hasanyrole('Administrator|HR|HR Head|Campus Director|Supervisor|Employee')
                    @if(Auth::user()->hasAnyRole(['Administrator', 'Campus Director', 'Supervisor']))
                        <x-responsive-nav-link :href="route('office-targets.index')" :active="$routeIs('office-targets.*')">
                            {{ __('Targets') }}
                        </x-responsive-nav-link>
                    @else
                        <x-responsive-nav-link :href="route('performance-targets.index')" :active="$routeIs('performance-targets.*')">
                            {{ __('Targets') }}
                        </x-responsive-nav-link>
                    @endif
                    <x-responsive-nav-link :href="route('accomplishments.index')" :active="$routeIs('accomplishments.*')">
                        {{ __('Accomplishments') }}
                    </x-responsive-nav-link>
                    <x-responsive-nav-link :href="route('movs.index')" :active="$routeIs('movs.*')">
                        {{ __('MOV') }}
                    </x-responsive-nav-link>
                @endhasanyrole
            </div>
            <div class="spms-sidebar-footer border-t border-white/15 mx-5 py-5">
                <span class="inline-block rounded-full border border-white/25 px-3 py-2 text-xs tracking-widest">SOUTHERN LEYTE STATE UNIVERSITY</span>
                <p class="mt-3 text-xs text-white/60">Performance management portal</p>
            </div>
        </div>
    </div>
</nav>
