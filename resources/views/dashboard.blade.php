<x-app-layout>
    <p data-dashboard-refresh role="status" class="px-6 pt-4 text-xs text-gray-500">Updates every 15 seconds while visible. Last loaded: {{ now()->format('H:i:s') }}</p>
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
                    $palette = ['#16325c', '#159a8c', '#f5a623', '#d9534f', '#4f78c4', '#7c5cbf', '#35a853', '#e8793e', '#64748b'];
                    $grandTotal = array_sum($counts);
                @endphp

                <div class="px-4 sm:px-0">
                    <div class="rounded-2xl bg-[#173967] p-6 text-white shadow-[0_16px_36px_-25px_rgba(18,45,86,0.7)] sm:flex sm:items-center sm:justify-between sm:p-8">
                        <div>
                            <p class="text-xs font-semibold uppercase tracking-[0.18em] text-[#a9dcf5]">System administrator overview</p>
                            <h1 class="mt-2 text-2xl font-bold">All SPMS records at a glance</h1>
                            <p class="mt-1 text-sm text-white/70">Live totals and distributions across the entire system.</p>
                        </div>
                        <div class="mt-5 rounded-xl border border-white/15 bg-white/10 px-6 py-4 text-center sm:mt-0">
                            <p class="text-3xl font-bold">{{ number_format($grandTotal) }}</p>
                            <p class="text-xs font-semibold uppercase tracking-wider text-white/70">Total records</p>
                        </div>
                    </div>

                    <section class="mt-8" aria-labelledby="system-totals">
                        <h2 id="system-totals" class="text-lg font-bold text-[#172f54]">System totals</h2>
                        <div class="mt-4 grid grid-cols-2 gap-4 sm:grid-cols-3 lg:grid-cols-4">
                            @foreach($counts as $label => $count)
                                <div class="rounded-2xl border border-slate-200 bg-white p-5 shadow-sm">
                                    <span class="mb-4 block h-1 w-9 rounded-full bg-[#69bde6]"></span>
                                    <p class="text-3xl font-bold tracking-tight text-[#173967]">{{ number_format($count) }}</p>
                                    <p class="mt-1 text-xs font-semibold uppercase tracking-wide text-slate-500">{{ $label }}</p>
                                </div>
                            @endforeach
                        </div>
                    </section>

                    <section class="mt-10" aria-labelledby="system-charts">
                        <h2 id="system-charts" class="text-lg font-bold text-[#172f54]">System distributions</h2>
                        <p class="mt-1 text-sm text-slate-500">Counts and shares for each category.</p>
                        <div class="mt-4 grid gap-5 md:grid-cols-2 xl:grid-cols-3">
                            @foreach($charts as $chart)
                                @php
                                    $chartTotal = array_sum($chart['values']);
                                    $cursor = 0;
                                    $segments = [];
                                    $segmentIndex = 0;
                                    foreach ($chart['values'] as $value) {
                                        if ($chartTotal > 0 && $value > 0) {
                                            $start = ($cursor / $chartTotal) * 100;
                                            $cursor += $value;
                                            $end = ($cursor / $chartTotal) * 100;
                                            $segments[] = $palette[$segmentIndex % count($palette)]." {$start}% {$end}%";
                                        }
                                        $segmentIndex++;
                                    }
                                    $pie = count($segments) ? 'conic-gradient('.implode(', ', $segments).')' : 'conic-gradient(#e5e7eb 0 100%)';
                                @endphp
                                <article class="rounded-2xl border border-slate-200 bg-white p-5 shadow-sm">
                                    <div class="flex items-start justify-between gap-4">
                                        <h3 class="font-bold text-[#172f54]">{{ $chart['title'] }}</h3>
                                        <span class="rounded-full bg-[#edf3fa] px-3 py-1 text-xs font-bold text-[#24477e]">{{ number_format($chartTotal) }} total</span>
                                    </div>
                                    <div class="mt-5 flex justify-center">
                                        <div class="relative h-32 w-32 rounded-full" style="background: {{ $pie }}" role="img" aria-label="{{ $chart['title'] }}: {{ number_format($chartTotal) }} total records">
                                            <div class="absolute inset-6 flex flex-col items-center justify-center rounded-full bg-white text-center">
                                                <span class="text-xl font-bold text-[#173967]">{{ number_format($chartTotal) }}</span>
                                                <span class="text-[10px] font-medium uppercase tracking-wide text-slate-400">records</span>
                                            </div>
                                        </div>
                                    </div>
                                    <div class="mt-5 space-y-3 text-xs">
                                        @forelse($chart['values'] as $label => $value)
                                            @php
                                                $percent = $chartTotal ? $value / $chartTotal * 100 : 0;
                                            @endphp
                                            <div>
                                                <div class="mb-1.5 flex items-center justify-between gap-3">
                                                    <span class="min-w-0 truncate font-medium text-slate-600" title="{{ $label }}">{{ ucfirst($label) }}</span>
                                                    <span class="shrink-0 font-semibold text-[#172f54]">{{ number_format($value) }} <span class="font-normal text-slate-400">({{ number_format($percent, 1) }}%)</span></span>
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
                $personalPalette = ['#159a8c', '#f5a623', '#d1d5db'];
                $personalCursor = 0;
                $personalSegments = [];
                foreach (array_values($personalDashboard['progress']) as $index => $value) {
                    if ($personalTotal > 0 && $value > 0) {
                        $start = ($personalCursor / $personalTotal) * 100;
                        $personalCursor += $value;
                        $end = ($personalCursor / $personalTotal) * 100;
                        $personalSegments[] = $personalPalette[$index]." {$start}% {$end}%";
                    }
                }
                $personalPie = count($personalSegments)
                    ? 'conic-gradient('.implode(', ', $personalSegments).')'
                    : 'conic-gradient(#e5e7eb 0 100%)';
            @endphp
            <div class="mb-6 px-4 sm:px-0">
                <div class="rounded-2xl bg-[#173967] p-6 text-white shadow-[0_16px_36px_-25px_rgba(18,45,86,0.7)] sm:p-8">
                    <p class="text-xs font-semibold uppercase tracking-[0.18em] text-[#a9dcf5]">{{ $primaryRole }} workspace</p>
                    <h1 class="mt-2 text-2xl font-bold sm:text-3xl">Welcome, {{ Auth::user()->name }}.</h1>
                    <p class="mt-2 max-w-2xl text-sm leading-6 text-white/75">{{ $roleDescriptions[$primaryRole] ?? 'See your assignments and progress in one place.' }}</p>
                    <p class="mt-4 inline-flex rounded-full border border-white/15 bg-white/10 px-3 py-1 text-xs font-medium text-white/85">{{ $user->office?->name ?? 'No office assigned' }}</p>
                    <div class="mt-5 flex flex-wrap gap-2">
                        @hasanyrole('Campus Director|Supervisor')
                            <a href="{{ route('office-targets.index') }}" class="rounded-lg bg-white px-4 py-2 text-xs font-semibold text-[#173967] transition hover:bg-[#eaf2fc] focus:outline-none focus:ring-2 focus:ring-spms-sky">View office targets</a>
                        @endhasanyrole
                        @role('Employee')
                            <a href="{{ route('performance-targets.index') }}" class="rounded-lg bg-white px-4 py-2 text-xs font-semibold text-[#173967] transition hover:bg-[#eaf2fc] focus:outline-none focus:ring-2 focus:ring-spms-sky">View my targets</a>
                        @endrole
                        @hasanyrole('HR|HR Head|HRM Office II')
                            <a href="{{ route('reports.index') }}" class="rounded-lg bg-white px-4 py-2 text-xs font-semibold text-[#173967] transition hover:bg-[#eaf2fc] focus:outline-none focus:ring-2 focus:ring-spms-sky">Review reports</a>
                        @endhasanyrole
                    </div>
                </div>
            </div>
            <div class="mb-6 px-4 sm:px-0">
                <div class="grid gap-4 sm:grid-cols-3">
                    <div class="rounded-2xl border border-slate-200 bg-white p-5 shadow-sm">
                        <span class="mb-4 block h-1 w-9 rounded-full bg-[#69bde6]"></span>
                        <p class="text-3xl font-bold tracking-tight text-[#173967]">{{ number_format($personalDashboard['assignedTargets']) }}</p>
                        <p class="mt-1 text-xs font-semibold uppercase tracking-wide text-slate-500">Total Assigned Targets</p>
                    </div>
                    <div class="rounded-2xl border border-slate-200 bg-white p-5 shadow-sm">
                        <span class="mb-4 block h-1 w-9 rounded-full bg-[#f5a623]"></span>
                        <p class="text-3xl font-bold tracking-tight text-[#173967]">{{ number_format($personalDashboard['submittedAccomplishments']) }}</p>
                        <p class="mt-1 text-xs font-semibold uppercase tracking-wide text-slate-500">Total Accomplishments</p>
                    </div>
                    <div class="rounded-2xl border border-slate-200 bg-white p-5 shadow-sm">
                        <span class="mb-4 block h-1 w-9 rounded-full bg-[#159a8c]"></span>
                        <p class="text-3xl font-bold tracking-tight text-[#173967]">{{ number_format($personalDashboard['accomplishedTargets']) }}</p>
                        <p class="mt-1 text-xs font-semibold uppercase tracking-wide text-slate-500">Accomplished Targets</p>
                    </div>
                </div>

                <div class="mt-5 rounded-2xl border border-slate-200 bg-white p-6 shadow-sm">
                    <h2 class="text-lg font-bold text-[#172f54]">Target Accomplishment Status</h2>
                    <p class="mt-1 text-sm text-slate-500">How your assigned targets are progressing.</p>
                    <div class="mt-5 flex flex-col items-center gap-8 sm:flex-row">
                        <div class="relative h-44 w-44 shrink-0 rounded-full" style="background: {{ $personalPie }}" role="img" aria-label="Target accomplishment status: {{ number_format($personalTotal) }} targets">
                            <div class="absolute inset-9 flex flex-col items-center justify-center rounded-full bg-white">
                                <span class="text-2xl font-bold text-[#173967]">{{ number_format($personalTotal) }}</span>
                                <span class="text-xs text-slate-500">targets</span>
                            </div>
                        </div>
                        <div class="w-full max-w-md space-y-3">
                            @foreach($personalDashboard['progress'] as $label => $value)
                                @php
                                    $percent = $personalTotal ? $value / $personalTotal * 100 : 0;
                                @endphp
                                <div>
                                    <div class="mb-1.5 flex items-center justify-between gap-3 text-sm">
                                        <span class="font-medium text-slate-600">{{ $label }}</span>
                                        <span class="font-bold text-[#172f54]">{{ number_format($value) }} <span class="font-normal text-slate-400">({{ number_format($percent, 1) }}%)</span></span>
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
                            <h2 id="employee-progress-heading" class="text-lg font-bold text-[#172f54]">Employee Progress</h2>
                            <p class="mt-1 text-sm text-slate-500">{{ Auth::user()->hasRole('Supervisor') ? 'Target and MOV progress for employees in your office.' : 'Employee progress in your campus and on targets you assigned to offices.' }} Updates every 15 seconds while this page is visible.</p>
                        </div>
                        <div class="overflow-x-auto">
                            <table class="min-w-full divide-y divide-slate-100">
                                <thead class="bg-[#f6f9fd]">
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
