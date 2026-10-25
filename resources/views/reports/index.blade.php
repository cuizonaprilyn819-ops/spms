<x-app-layout>
    <x-slot name="header">
        <div>
            <h2 class="text-xl font-bold leading-tight text-spms-ink">My Reports</h2>
            <p class="mt-1 text-sm text-gray-500">Prepare and find your report forms in one place.</p>
        </div>
    </x-slot>

    <div class="py-8">
        <div class="mx-auto max-w-7xl px-4 sm:px-6 lg:px-8">
            <section class="mb-10" aria-labelledby="my-report-forms-heading">
                <h3 id="my-report-forms-heading" class="text-lg font-bold text-[#172f54]">My report forms</h3>
                <p class="mt-1 text-sm text-slate-500">Forms you prepare depend on your role. Your assigned IPCR also appears here.</p>
                <div class="mt-4 grid gap-4 lg:grid-cols-2">
                    @foreach ($myReportTypes as $type => $label)
                        <div class="rounded-xl border border-slate-200 bg-white p-5 shadow-sm">
                            <div class="flex flex-wrap items-start justify-between gap-3">
                                <div>
                                    <h4 class="text-base font-bold text-[#172f54]">{{ strtoupper($type) }}</h4>
                                    <p class="mt-1 text-sm text-slate-500">{{ $label }}</p>
                                </div>
                                @if(Auth::user()->office_id)
                                    <a href="{{ route($type.'s.create') }}" class="rounded-lg bg-spms-navy px-4 py-2 text-sm font-semibold text-white hover:bg-[#2b4276]">Prepare {{ strtoupper($type) }}</a>
                                @endif
                            </div>
                            <div class="mt-4 divide-y divide-slate-100 border-t border-slate-100">
                                @forelse ($myReports[$type] as $report)
                                    <a href="{{ route($type.'s.show', $report) }}" class="flex items-center justify-between gap-3 py-3 text-sm hover:text-spms-navy">
                                        <span class="min-w-0 truncate font-semibold text-[#172f54]">{{ $report->title }}</span>
                                        <span class="shrink-0 rounded-full bg-slate-100 px-2.5 py-1 text-xs font-semibold capitalize text-slate-600">{{ $report->status }}</span>
                                    </a>
                                @empty
                                    <p class="py-4 text-sm text-slate-500">No {{ strtoupper($type) }} forms yet.</p>
                                @endforelse
                            </div>
                            @if ($myReports[$type]->hasPages())
                                <div class="mt-3">{{ $myReports[$type]->links() }}</div>
                            @endif
                        </div>
                    @endforeach
                </div>
            </section>
            <section aria-labelledby="finished-reports-heading">
                <div class="flex flex-wrap items-end justify-between gap-3">
                    <div>
                        <h3 id="finished-reports-heading" class="text-lg font-bold text-[#172f54]">Finished reports</h3>
                        <p class="mt-1 text-sm text-slate-500">Approved reports are added here automatically.</p>
                    </div>
                    <span class="rounded-full bg-[#eaf2fc] px-3 py-1 text-xs font-semibold text-[#24477e]">{{ number_format(array_sum($finishedCounts)) }} approved</span>
                </div>

                <div class="mt-4 flex flex-wrap gap-2" aria-label="Filter finished reports">
                    <a href="{{ route('reports.index') }}" class="rounded-full px-4 py-2 text-xs font-semibold {{ $selectedType === null ? 'bg-[#173967] text-white' : 'bg-white text-[#24477e] ring-1 ring-slate-200 hover:bg-[#eaf2fc]' }}">All</a>
                    @foreach($reports as $type => $title)
                        <a href="{{ route('reports.index', ['type' => $type]) }}" class="rounded-full px-4 py-2 text-xs font-semibold {{ $selectedType === $type ? 'bg-[#173967] text-white' : 'bg-white text-[#24477e] ring-1 ring-slate-200 hover:bg-[#eaf2fc]' }}">{{ strtoupper($type) }} <span class="opacity-70">{{ number_format($finishedCounts[$type]) }}</span></a>
                    @endforeach
                </div>

                <div class="mt-4 overflow-hidden rounded-2xl border border-slate-200 bg-white shadow-sm">
                    <div class="overflow-x-auto">
                        <table class="min-w-full divide-y divide-slate-100">
                            <thead class="bg-[#f6f9fd] text-left text-xs font-semibold uppercase tracking-wide text-slate-500">
                                <tr>
                                    <th class="px-5 py-3">Report</th>
                                    <th class="px-5 py-3">Prepared by</th>
                                    <th class="px-5 py-3">Approved by</th>
                                    <th class="px-5 py-3">Finished</th>
                                    <th class="px-5 py-3 text-right">Actions</th>
                                </tr>
                            </thead>
                            <tbody class="divide-y divide-slate-100">
                                @forelse($finishedReports as $item)
                                    <tr class="hover:bg-slate-50/70">
                                        <td class="min-w-56 px-5 py-4">
                                            <span class="text-xs font-bold uppercase text-[#3979a8]">{{ strtoupper($item['type']) }}</span>
                                            <p class="mt-1 text-sm font-semibold text-[#172f54]">{{ $item['title'] }}</p>
                                        </td>
                                        <td class="px-5 py-4 text-sm text-slate-600">{{ $item['prepared_by'] }}</td>
                                        <td class="px-5 py-4 text-sm text-slate-600">{{ $item['approved_by'] }}</td>
                                        <td class="whitespace-nowrap px-5 py-4 text-sm text-slate-600">{{ $item['approved_at']?->format('M d, Y') ?? '—' }}</td>
                                        <td class="whitespace-nowrap px-5 py-4 text-right text-xs font-semibold">
                                            <a class="text-[#24477e] hover:underline" href="{{ route('reports.finished', [$item['type'], $item['id']]) }}">View</a>
                                            <a class="ml-3 text-[#24477e] hover:underline" href="{{ route('reports.finished.pdf', [$item['type'], $item['id']]) }}">PDF</a>
                                            <a class="ml-3 text-[#24477e] hover:underline" href="{{ route('reports.finished.excel', [$item['type'], $item['id']]) }}">Excel</a>
                                        </td>
                                    </tr>
                                @empty
                                    <tr><td colspan="5" class="px-5 py-12 text-center text-sm text-slate-500">No finished reports yet. Approved reports will appear here.</td></tr>
                                @endforelse
                            </tbody>
                        </table>
                    </div>
                </div>
                @if($selectedType)
                    <div class="mt-4">{{ $finishedReports->links() }}</div>
                @endif
            </section>

        </div>
    </div>
</x-app-layout>
