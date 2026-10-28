<x-app-layout>
    <x-slot name="header">
        <div>
            <h2 class="text-xl font-bold leading-tight text-spms-ink">All Ratings</h2>
            <p class="mt-1 text-sm text-gray-500">Review rated work for employees and supervisors across all offices.</p>
        </div>
    </x-slot>

    <div class="py-8">
        <div class="mx-auto max-w-7xl space-y-6 px-4 sm:px-6 lg:px-8">
            <div class="flex flex-wrap items-center justify-between gap-4 rounded-xl border border-spms-line bg-white px-5 py-4 shadow-sm">
                <div>
                    <p class="text-xs font-bold uppercase tracking-widest text-spms-navy">University wide</p>
                    <p class="mt-1 text-sm text-gray-600">{{ $evaluations->total() }} {{ \Illuminate\Support\Str::plural('target evaluation', $evaluations->total()) }} · {{ $ratedIpcrCount }} rated {{ \Illuminate\Support\Str::plural('IPCR', $ratedIpcrCount) }} · {{ $unratedIpcrCount }} approved without scores</p>
                </div>
                <form method="GET" action="{{ route('ratings.index') }}" class="flex items-center gap-2">
                    <label for="role" class="text-sm font-semibold text-spms-ink">Show</label>
                    <select id="role" name="role" onchange="this.form.submit()" class="rounded-lg border-gray-300 text-sm text-spms-ink focus:border-spms-navy focus:ring-spms-navy">
                        <option value="" @selected(!$role)>Employees and supervisors</option>
                        <option value="Employee" @selected($role === 'Employee')>Employees</option>
                        <option value="Supervisor" @selected($role === 'Supervisor')>Supervisors</option>
                    </select>
                    <button type="submit" class="rounded-lg bg-spms-navy px-4 py-2 text-sm font-semibold text-white">Apply</button>
                </form>
                <a href="{{ route('ratings.pdf', $role ? ['role' => $role] : []) }}" class="rounded-lg border border-spms-navy px-4 py-2 text-sm font-semibold text-spms-navy hover:bg-spms-tint">Export ratings PDF</a>
            </div>

            <section class="overflow-hidden rounded-xl border border-gray-200 bg-white shadow-sm" aria-labelledby="evaluation-ratings-heading">
                <div class="border-b border-gray-200 px-6 py-5">
                    <h3 id="evaluation-ratings-heading" class="text-lg font-bold text-spms-ink">Target evaluations</h3>
                    <p class="mt-1 text-sm text-gray-500">Ratings entered for performance targets.</p>
                </div>
                <div class="overflow-x-auto">
                    <table class="min-w-full divide-y divide-gray-200 text-sm">
                        <thead class="bg-spms-surface text-left text-xs font-bold uppercase text-gray-500">
                            <tr>
                                <th class="px-6 py-3">Personnel</th>
                                <th class="px-6 py-3">Target</th>
                                <th class="px-6 py-3">Q / E / T</th>
                                <th class="px-6 py-3">Average</th>
                                <th class="px-6 py-3">Weighted</th>
                                <th class="px-6 py-3">Rated by</th>
                                <th class="px-6 py-3 text-right">Details</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-gray-100">
                            @forelse ($evaluations as $evaluation)
                                <tr>
                                    <td class="px-6 py-4">
                                        <p class="font-semibold text-spms-ink">{{ $evaluation->employee?->name }}</p>
                                        <p class="text-xs text-gray-500">{{ $evaluation->employee?->hasRole('Supervisor') ? 'Supervisor' : 'Employee' }} · {{ $evaluation->employee?->office?->name ?? 'No office' }}</p>
                                    </td>
                                    <td class="px-6 py-4 text-gray-700">{{ $evaluation->performanceTarget?->target ?? 'Target unavailable' }}</td>
                                    <td class="whitespace-nowrap px-6 py-4 text-gray-700">{{ number_format((float) ($evaluation->quality_rating ?? $evaluation->rating), 2) }} / {{ number_format((float) ($evaluation->efficiency_rating ?? $evaluation->rating), 2) }} / {{ number_format((float) ($evaluation->timeliness_rating ?? $evaluation->rating), 2) }}</td>
                                    <td class="px-6 py-4 font-bold text-spms-navy">{{ number_format((float) $evaluation->rating, 2) }}<span class="block text-xs font-medium text-gray-500">{{ $evaluation->adjectival_rating }}</span></td>
                                    <td class="px-6 py-4 font-semibold text-spms-green">{{ number_format((float) $evaluation->computed_rating, 2) }}</td>
                                    <td class="px-6 py-4 text-gray-600">{{ $evaluation->ratedBy?->name ?? 'Unknown' }}</td>
                                    <td class="px-6 py-4 text-right"><a class="font-semibold text-spms-navy hover:underline" href="{{ route('evaluations.show', $evaluation) }}">View</a></td>
                                </tr>
                            @empty
                                <tr><td colspan="7" class="px-6 py-10 text-center text-gray-500">No target evaluations found.</td></tr>
                            @endforelse
                        </tbody>
                    </table>
                </div>
                @if ($evaluations->hasPages()) <div class="border-t border-gray-200 px-6 py-4">{{ $evaluations->links() }}</div> @endif
            </section>

            <section class="overflow-hidden rounded-xl border border-gray-200 bg-white shadow-sm" aria-labelledby="ipcr-ratings-heading">
                <div class="border-b border-gray-200 px-6 py-5">
                    <h3 id="ipcr-ratings-heading" class="text-lg font-bold text-spms-ink">IPCR reviews</h3>
                    <p class="mt-1 text-sm text-gray-500">Approved IPCRs, including records whose reviewer scores have not been saved.</p>
                </div>
                <div class="overflow-x-auto">
                    <table class="min-w-full divide-y divide-gray-200 text-sm">
                        <thead class="bg-spms-surface text-left text-xs font-bold uppercase text-gray-500">
                            <tr>
                                <th class="px-6 py-3">Personnel</th>
                                <th class="px-6 py-3">IPCR</th>
                                <th class="px-6 py-3">Q / E / T</th>
                                <th class="px-6 py-3">Average</th>
                                <th class="px-6 py-3">Reviewed by</th>
                                <th class="px-6 py-3 text-right">Details</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-gray-100">
                            @forelse ($ipcrRatings as $ipcr)
                                <tr>
                                    <td class="px-6 py-4">
                                        <p class="font-semibold text-spms-ink">{{ $ipcr->employee?->name }}</p>
                                        <p class="text-xs text-gray-500">{{ $ipcr->employee?->hasRole('Supervisor') ? 'Supervisor' : 'Employee' }} · {{ $ipcr->employee?->office?->name ?? 'No office' }}</p>
                                    </td>
                                    <td class="px-6 py-4 text-gray-700">{{ $ipcr->title }}</td>
                                    <td class="whitespace-nowrap px-6 py-4 text-gray-700">@if ($ipcr->average_rating !== null){{ number_format((float) $ipcr->quality_rating, 2) }} / {{ number_format((float) $ipcr->efficiency_rating, 2) }} / {{ number_format((float) $ipcr->timeliness_rating, 2) }}@else<span class="text-gray-400">—</span>@endif</td>
                                    <td class="px-6 py-4 font-bold text-spms-navy">@if ($ipcr->average_rating !== null){{ number_format((float) $ipcr->average_rating, 2) }}@else<span class="rounded-full bg-amber-50 px-2.5 py-1 text-xs font-semibold text-amber-800">Not rated</span>@endif</td>
                                    <td class="px-6 py-4 text-gray-600">{{ $ipcr->approvedBy?->name ?? 'Unknown' }}</td>
                                    <td class="px-6 py-4 text-right"><a class="font-semibold text-spms-navy hover:underline" href="{{ route('ipcrs.show', $ipcr) }}">View</a></td>
                                </tr>
                            @empty
                                <tr><td colspan="6" class="px-6 py-10 text-center text-gray-500">No approved IPCRs found.</td></tr>
                            @endforelse
                        </tbody>
                    </table>
                </div>
                @if ($ipcrRatings->hasPages()) <div class="border-t border-gray-200 px-6 py-4">{{ $ipcrRatings->links() }}</div> @endif
            </section>
        </div>
    </div>
</x-app-layout>
