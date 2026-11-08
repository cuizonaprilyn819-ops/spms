<nav aria-label="Main navigation" x-data="{ open: false }" @keydown.escape="if (open) { open = false; $refs.menuToggle.focus() }" class="spms-sidebar text-white md:sticky md:top-0 md:flex md:h-screen md:shrink-0 md:flex-col md:overflow-hidden">
    @php
        $routeIs = fn (...$patterns) => request()->routeIs(...[...$patterns, ...array_map(fn ($pattern) => 'api.'.$pattern, $patterns)]);
        $navigationUser = Auth::user()->loadMissing(['office', 'roles']);
    @endphp
    <div class="spms-brand flex items-center justify-between px-5 py-5">
        <a href="{{ route('dashboard') }}" class="flex min-w-0 items-center gap-3">
            <img src="{{ asset('images/logoslsu.png') }}" alt="Southern Leyte State University logo" class="h-11 w-11 shrink-0 rounded-full object-contain">
            <span class="min-w-0"><span class="block truncate text-lg font-semibold tracking-tight text-white">SLSU <span class="font-normal text-white/70">SPMS</span></span><span class="block text-[11px] text-white/65">Performance workspace</span></span>
        </a>
        <button x-ref="menuToggle" type="button" @click="open = !open" :aria-expanded="open" aria-controls="workspace-navigation" class="spms-menu-toggle gap-2 px-2 text-sm font-semibold md:hidden" aria-label="Toggle navigation menu"><svg class="h-5 w-5" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" aria-hidden="true"><path d="M4 6h16M4 12h16M4 18h16"/></svg><span>Menu</span></button>
    </div>
    <div id="workspace-navigation" :class="{'block': open, 'hidden': !open}" class="hidden md:min-h-0 md:flex-1 md:block">
        <div class="flex h-full flex-col">
            <div class="spms-sidebar-scroll min-h-0 flex-1 overflow-y-auto px-3 py-3">
                <p class="spms-nav-heading">Workspace</p>
                <x-responsive-nav-link :href="route('dashboard')" :active="$routeIs('dashboard')">Dashboard</x-responsive-nav-link>
                <x-responsive-nav-link :href="route('reports.index')" :active="$routeIs('reports.*', 'opcrs.*', 'dpcrs.*', 'upcrs.*', 'ipcrs.*', 'report-workflow.*')">My Reports</x-responsive-nav-link>
                <x-responsive-nav-link :href="route('notifications.index')" :active="$routeIs('notifications.*')"><span class="flex items-center justify-between gap-2">Notifications @if($unreadCount)<span class="spms-nav-count">{{ $unreadCount > 99 ? '99+' : $unreadCount }}</span>@endif</span></x-responsive-nav-link>
                <p class="spms-nav-heading">Performance work</p>
                @role('Administrator')<x-responsive-nav-link :href="route('planning.campuses')" :active="$routeIs('planning.campuses')">Campuses &amp; planning access</x-responsive-nav-link>@endrole
                <x-responsive-nav-link :href="route('planning.index')" :active="$routeIs('planning.*')">Commitment Planning</x-responsive-nav-link>
                @if(config('spms.target_assistance.enabled'))<x-responsive-nav-link :href="route('target-conflicts.index')" :active="$routeIs('target-conflicts.*')">Target conflicts</x-responsive-nav-link>@endif
                @hasanyrole('Administrator|Planning Officer')
                    <x-responsive-nav-link :href="route('target-documents.index')" :active="$routeIs('target-documents.*', 'target-drafts.*')">Planning Documents</x-responsive-nav-link>
                @endhasanyrole
                @hasanyrole('Administrator|Planning Officer|HR|HR Head|Campus Director|Supervisor|Division Head|Unit Head|Employee')
                    @if($navigationUser->hasAnyRole(['Administrator', 'Planning Officer', 'Campus Director', 'Supervisor', 'Division Head', 'Unit Head']))
                        <x-responsive-nav-link :href="route('office-targets.index')" :active="$routeIs('office-targets.*')">Office Targets</x-responsive-nav-link>
                    @else
                        <x-responsive-nav-link :href="route('performance-targets.index')" :active="$routeIs('performance-targets.*', 'office-targets.show')">My Targets</x-responsive-nav-link>
                    @endif
                    <x-responsive-nav-link :href="route('accomplishments.index')" :active="$routeIs('accomplishments.*')">Accomplishments</x-responsive-nav-link>
                    <x-responsive-nav-link :href="route('movs.index')" :active="$routeIs('movs.*')">Evidence / MOV</x-responsive-nav-link>
                @endhasanyrole
                @hasanyrole('Administrator|Planning Officer|HR|HR Head|Campus Director|Supervisor|Division Head|Unit Head|HRM Office II')
                    <x-responsive-nav-link :href="route('evaluations.index')" :active="$routeIs('evaluations.*')">Evaluations</x-responsive-nav-link>
                @endhasanyrole
                @hasanyrole('HR|HR Head')
                    <x-responsive-nav-link :href="route('ratings.index')" :active="$routeIs('ratings.*')">All Ratings</x-responsive-nav-link>
                @endhasanyrole
                <x-responsive-nav-link :href="route('historical-pcrs.index')" :active="$routeIs('historical-pcrs.*')">Historical PCRs</x-responsive-nav-link>
                @if($navigationUser->hasAnyRole(['Administrator', 'HR', 'HR Head', 'HRM Office II', 'Planning Officer', 'Campus Director']))<p class="spms-nav-heading">Organisation</p>@endif
                @hasanyrole('Administrator|HR|HR Head|HRM Office II')
                    <x-responsive-nav-link :href="route('offices.index')" :active="$routeIs('offices.*')">Offices</x-responsive-nav-link>
                    <x-responsive-nav-link :href="route('employees.index')" :active="$routeIs('employees.*')">Personnel</x-responsive-nav-link>
                @endhasanyrole
                @hasanyrole('Administrator|HR|HR Head|Planning Officer')
                    <x-responsive-nav-link :href="route('evaluation-cycles.index')" :active="$routeIs('evaluation-cycles.*')">Evaluation Periods</x-responsive-nav-link>
                @endhasanyrole
                @hasanyrole('Administrator|Planning Officer|Campus Director')
                    <x-responsive-nav-link :href="route('strategic-goals.index')" :active="$routeIs('strategic-goals.*', 'strategic-objectives.*')">Strategy</x-responsive-nav-link>
                @endhasanyrole
            </div>
            <div class="spms-sidebar-footer mx-5 border-t border-white/15 py-4"><a href="{{ route('profile.edit') }}" class="flex items-center gap-3"><span class="spms-account-avatar" aria-hidden="true">{{ mb_substr($navigationUser->name, 0, 1) }}</span><span class="min-w-0"><span class="block truncate text-xs font-semibold text-white">{{ $navigationUser->name }}</span><span class="block truncate text-[11px] text-white/65">{{ $navigationUser->roles->pluck('name')->join(', ') }}</span></span></a><p class="mt-3 text-[11px] text-white/60">{{ $navigationUser->office?->name ?? 'Southern Leyte State University' }}</p></div>
        </div>
    </div>
</nav>
