<x-app-layout>
    <x-slot name="header">
        <div class="flex flex-col gap-4 sm:flex-row sm:items-center sm:justify-between">
            <div>
                <h2 class="text-xl font-bold leading-tight text-spms-ink">{{ $title }}</h2>
                <p class="mt-1 text-sm text-gray-500">{{ $archiveId ? 'Approved report archived in Reports.' : 'Preview generated '.$generatedAt->format('F d, Y h:i A').'.' }}</p>
            </div>
            <div class="flex flex-wrap gap-2">
                @if($canExport)
                <a href="{{ $archiveId ? route('reports.finished.pdf', [$type, $archiveId]) : route('reports.pdf', $type) }}" class="rounded-lg bg-spms-navy px-5 py-2.5 text-sm font-semibold text-white transition hover:bg-[#2b4276]">Export PDF</a>
                <a href="{{ $archiveId ? route('reports.finished.excel', [$type, $archiveId]) : route('reports.excel', $type) }}" class="rounded-lg border border-gray-300 px-5 py-2.5 text-sm font-semibold text-spms-ink transition hover:bg-gray-50">Export Excel</a>
                @else
                    <p class="text-sm text-gray-500">PDF and Excel export will be available after IPCR approval.</p>
                @endif
                @if(Auth::user()->usesMyReports() && $canExport)<p class="text-xs text-gray-500">Exports include approved IPCR records only.</p>@endif
            </div>
        </div>
    </x-slot>

    <div class="py-8">
        <div class="mx-auto max-w-7xl px-4 sm:px-6 lg:px-8">
            <div class="mb-6 overflow-hidden rounded-lg border border-gray-200 bg-white shadow-sm">
                <div class="overflow-x-auto">
                    @include('reports.partials.table')
                </div>
            </div>

            @include('reports.partials.form-report')
        </div>
    </div>
</x-app-layout>
