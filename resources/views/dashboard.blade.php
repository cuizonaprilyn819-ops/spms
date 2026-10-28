<x-app-layout>
    <p data-dashboard-refresh role="status" class="px-6 pt-4 text-xs text-gray-500">Updates every 15 seconds while visible. Last loaded: {{ now()->format('H:i:s') }}</p>
    @include('planning.dashboard')
    @php
        $user = Auth::user()->loadMissing(['office', 'roles']);
        $officeRoleLabel = ($user->office?->name ?? 'No Office Assigned').' - '.($user->roles->pluck('name')->join(', ') ?: 'No Role Assigned');
        $primaryRole = $user->roles->first()?->name ?? 'Team member';
        $roleDescriptions = [
            'Campus Director' => 'Review campus commitments and follow progress across your teams.',
            'Supervisor' => 'Keep office targets, submissions, and employee progress in view.',
            'HR Head' => 'Monitor personnel progress and performance records.',
            'HR' => 'Review your targets and keep your performance records on track.',
            'HRM Office II' => 'Stay on top of your reports and assigned commitments.',
            'Employee' => 'Track your targets and the work you have submitted.',
        ];
    @endphp

    <x-slot name="header">
        <div>
            <h2 class="text-xl font-bold leading-tight text-spms-ink">Dashboard</h2>
            <p class="mt-1 text-xs font-medium text-slate-500">{{ $officeRoleLabel }}</p>
        </div>
    </x-slot>

    <div class="py-8">
        <div class="max-w-7xl mx-auto sm:px-6 lg:px-8">
            @role('Administrator')
                @php
                    $palette = ['#34508e', '#22736d', '#a16207', '#b42318', '#526475', '#687b8e'];
                @endphp

                <div class="px-4 sm:px-0">
                    <div class="spms-dashboard-intro">
                        <div>
                            <p class="text-xs font-medium text-slate-500">System administrator overview</p>
                            <h1 class="mt-1 text-xl font-semibold">All SPMS records at a glance</h1>
                            <p class="mt-1 text-sm text-slate-500">Review personnel, reports, and supporting work across the university.</p>
                        </div>
                    </div>

                    <section class="mt-8" aria-labelledby="system-totals">
                        <h2 id="system-totals" class="text-lg font-bold text-spms-ink">System totals</h2>
                        <div class="mt-4 grid grid-cols-2 overflow-hidden rounded-lg border border-slate-200 sm:grid-cols-3 lg:grid-cols-4">
                            @foreach($counts as $label => $count)
                                <div class="spms-dashboard-count">
                                    <p>{{ number_format($count) }}</p>
                                    <p>{{ $label }}</p>
                                </div>
                            @endforeach
                        </div>
                    </section>

                    <section class="mt-10" aria-labelledby="system-charts">
                        <h2 id="system-charts" class="text-lg font-bold text-spms-ink">System distributions</h2>
                        <p class="mt-1 text-sm text-slate-500">Counts and shares for each category.</p>
                        <div class="mt-4 grid gap-5 md:grid-cols-2 xl:grid-cols-3">
                            @foreach($charts as $chart)
                                @php
                                    $chartTotal = array_sum($chart['values']);
                                @endphp
                                <article class="rounded-lg border border-slate-200 bg-white p-5">
                                    <div class="flex items-start justify-between gap-4">
                                        <h3 class="font-bold text-spms-ink">{{ $chart['title'] }}</h3>
                                        <span class="rounded-full bg-spms-tint px-3 py-1 text-xs font-bold text-spms-navy">{{ number_format($chartTotal) }} total</span>
                                    </div>
                                    <div class="mt-5 space-y-3 text-xs">
                                        @forelse($chart['values'] as $label => $value)
                                            @php
                                                $percent = $chartTotal ? $value / $chartTotal * 100 : 0;
                                            @endphp
                                            <div>
                                                <div class="mb-1.5 flex items-center justify-between gap-3">
                                                    <span class="min-w-0 truncate font-medium text-slate-600" title="{{ $label }}">{{ ucfirst($label) }}</span>
                                                    <span class="shrink-0 font-semibold text-spms-ink">{{ number_format($value) }} <span class="font-normal text-slate-400">({{ number_format($percent, 1) }}%)</span></span>
                                                </div>
                                                <div class="h-2 overflow-hidden rounded-full bg-slate-100" role="meter" aria-label="{{ $label }} share" aria-valuenow="{{ round($percent, 1) }}" aria-valuemin="0" aria-valuemax="100">
                                                    <div class="h-full rounded-full" style="width: {{ $percent }}%; background-color: {{ $palette[$loop->index % count($palette)] }}"></div>
                                                </div>
                                            </div>
                                        @empty
                                            <p class="rounded-lg bg-slate-50 px-3 py-4 text-center text-slate-500">No records yet</p>
                                        @endforelse
                                    </div>
                                </article>
                            @endforeach
                        </div>
                    </section>
                </div>
            @else
            @php
                $personalTotal = array_sum($personalDashboard['progress']);
                $personalPalette = ['#22736d', '#a16207', '#64748b'];
            @endphp
            <div class="mb-6 px-4 sm:px-0">
                <div class="spms-dashboard-intro">
                    <p class="text-xs font-medium text-slate-500">{{ $primaryRole }} workspace</p>
                    <h1 class="mt-1 text-xl font-semibold">Welcome, {{ Auth::user()->name }}.</h1>
                    <p class="mt-1 max-w-2xl text-sm text-slate-500">{{ $roleDescriptions[$primaryRole] ?? 'See your assignments and progress in one place.' }}</p>
                    <div class="mt-3 flex flex-wrap gap-2">
                        @hasanyrole('Campus Director|Supervisor')
                            <a href="{{ route('office-targets.index') }}" class="rounded-lg bg-white px-4 py-2 text-xs font-semibold text-spms-navy transition hover:bg-spms-tint focus:outline-none focus:ring-2 focus:ring-spms-sky">View office targets</a>
                        @endhasanyrole
                        @role('Employee')
                            <a href="{{ route('performance-targets.index') }}" class="rounded-lg bg-white px-4 py-2 text-xs font-semibold text-spms-navy transition hover:bg-spms-tint focus:outline-none focus:ring-2 focus:ring-spms-sky">View my targets</a>
                        @endrole
                        @hasanyrole('HR|HR Head|HRM Office II')
                            <a href="{{ route('reports.index') }}" class="rounded-lg bg-white px-4 py-2 text-xs font-semibold text-spms-navy transition hover:bg-spms-tint focus:outline-none focus:ring-2 focus:ring-spms-sky">Review reports</a>
                        @endhasanyrole
                    </div>
                </div>
            </div>
            <div class="mb-6 px-4 sm:px-0">
                <div class="grid overflow-hidden rounded-lg border border-slate-200 sm:grid-cols-3">
                    <div class="spms-dashboard-count">
                        <p>{{ number_format($personalDashboard['assignedTargets']) }}</p>
                        <p>Total Assigned Targets</p>
                    </div>
                    <div class="spms-dashboard-count">
                        <p>{{ number_format($personalDashboard['submittedAccomplishments']) }}</p>
                        <p>Total Accomplishments</p>
                    </div>
                    <div class="spms-dashboard-count">
                        <p>{{ number_format($personalDashboard['accomplishedTargets']) }}</p>
                        <p>Accomplished Targets</p>
                    </div>
                </div>

                <div class="mt-5 rounded-lg border border-slate-200 bg-white p-5">
                    <h2 class="text-base font-semibold text-spms-ink">Target Accomplishment Status</h2>
                    <p class="mt-1 text-sm text-slate-500">How your assigned targets are progressing.</p>
                    <div class="mt-5">
                        <div class="w-full space-y-3">
                            @foreach($personalDashboard['progress'] as $label => $value)
                                @php
                                    $percent = $personalTotal ? $value / $personalTotal * 100 : 0;
                                @endphp
                                <div>
                                    <div class="mb-1.5 flex items-center justify-between gap-3 text-sm">
                                        <span class="font-medium text-slate-600">{{ $label }}</span>
                                        <span class="font-bold text-spms-ink">{{ number_format($value) }} <span class="font-normal text-slate-400">({{ number_format($percent, 1) }}%)</span></span>
                                    </div>
                                    <div class="h-2.5 overflow-hidden rounded-full bg-slate-100" role="meter" aria-label="{{ $label }} target share" aria-valuenow="{{ round($percent, 1) }}" aria-valuemin="0" aria-valuemax="100">
                                        <div class="h-full rounded-full" style="width: {{ $percent }}%; background-color: {{ $personalPalette[$loop->index] }}"></div>
                                    </div>
                                </div>
                            @endforeach
                        </div>
                    </div>
                </div>

                @if($personalDashboard['employeeProgress']->isNotEmpty() || Auth::user()->hasAnyRole(['Campus Director', 'Supervisor', 'HR Head']))
                    <section class="mt-5 overflow-hidden rounded-2xl border border-slate-200 bg-white shadow-sm" aria-labelledby="employee-progress-heading">
                        <div class="border-b border-slate-100 px-6 py-5">
                            <h2 id="employee-progress-heading" class="text-lg font-bold text-spms-ink">Employee Progress</h2>
                            <p class="mt-1 text-sm text-slate-500">{{ Auth::user()->hasRole('Supervisor') ? 'Target and MOV progress for employees in your office.' : 'Employee progress in your campus and on targets you assigned to offices.' }} Updates every 15 seconds while this page is visible.</p>
                        </div>
                        <div class="overflow-x-auto">
                            <table class="min-w-full divide-y divide-slate-100">
                                <thead class="bg-spms-tint">
                                    <tr>
                                        <th class="px-6 py-3 text-left text-xs font-bold uppercase text-gray-500">Employee</th>
                                        <th class="px-6 py-3 text-left text-xs font-bold uppercase text-gray-500">Office</th>
                                        <th class="px-6 py-3 text-center text-xs font-bold uppercase text-gray-500">Targets</th>
                                        <th class="px-6 py-3 text-center text-xs font-bold uppercase text-gray-500">Completed</th>
                                        <th class="px-6 py-3 text-left text-xs font-bold uppercase text-gray-500">Progress</th>
                                    </tr>
                                </thead>
                                <tbody class="divide-y divide-slate-100">
                                    @forelse($personalDashboard['employeeProgress'] as $employeeProgress)
                                        <tr class="transition hover:bg-slate-50/70">
                                            <td class="px-6 py-4">
                                                <p class="text-sm font-semibold text-spms-ink">{{ $employeeProgress['name'] }}</p>
                                                <p class="mt-1 text-xs text-gray-500">{{ $employeeProgress['position'] }}</p>
                                            </td>
                                            <td class="px-6 py-4 text-sm text-gray-600">{{ $employeeProgress['office'] }}</td>
                                            <td class="px-6 py-4 text-center text-sm font-semibold text-spms-ink">{{ $employeeProgress['targets'] }}</td>
                                            <td class="px-6 py-4 text-center text-sm font-semibold text-spms-green">{{ $employeeProgress['completed'] }}</td>
                                            <td class="min-w-56 px-6 py-4">
                                                @if($employeeProgress['officeTargets'])
                                                    <p class="text-sm font-semibold">MOV submitted: {{ $employeeProgress['submitted'] }} / {{ $employeeProgress['officeTargets'] }} targets</p>
                                                    <div class="mt-2 h-2.5 overflow-hidden rounded-full bg-gray-200"><div class="h-full bg-spms-sky" style="width: {{ 100 * $employeeProgress['submitted'] / $employeeProgress['officeTargets'] }}%"></div></div>
                                                    <p class="mt-1 text-xs text-gray-500">{{ $employeeProgress['submitted'] ? 'Evidence submitted for supervisor review.' : 'Awaiting employee submission.' }}</p>
                                                @endif
                                                @if($employeeProgress['reportTargets'])
                                                <p class="mt-2 text-xs text-gray-500">Report target completion</p>
                                                <div class="flex items-center gap-3">
                                                    <div class="h-2.5 flex-1 overflow-hidden rounded-full bg-gray-200">
                                                        <div class="h-full rounded-full bg-spms-green" style="width: {{ min(100, $employeeProgress['average']) }}%"></div>
                                                    </div>
                                                    <span class="w-14 text-right text-sm font-bold text-spms-ink">{{ number_format($employeeProgress['average'], 1) }}%</span>
                                                </div>
                                                @elseif(! $employeeProgress['officeTargets'])
                                                    <span class="text-sm text-gray-500">No targets assigned</span>
                                                @endif
                                            </td>
                                        </tr>
                                    @empty
                                        <tr>
                                            <td colspan="5" class="px-6 py-12 text-center text-sm text-gray-500">{{ Auth::user()->hasRole('Supervisor') ? 'No Employee-role users are assigned to your office yet.' : 'No employees found in your campus or assigned targets.' }}</td>
                                        </tr>
                                    @endforelse
                                </tbody>
                            </table>
                        </div>
                    </section>
                @endif
            </div>
            @endrole
        </div>
    </div>
    <div class="px-6 pb-6">
        <x-office-target-progress :rows="$officeProgress" />
        <x-target-activity-log :activity="$targetActivity" />
    </div>
</x-app-layout>
