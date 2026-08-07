<x-app-layout>
    <x-slot name="header">
        <div>
            <h2 class="text-xl font-bold leading-tight text-spms-ink">{{ __('Reports') }}</h2>
            <p class="mt-1 text-sm text-gray-500">Generate OPCR, DPCR, UPCR, and IPCR reports, then export them to PDF or Excel.</p>
        </div>
    </x-slot>

    <div class="py-8">
        <div class="mx-auto max-w-7xl px-4 sm:px-6 lg:px-8">
            <div class="grid gap-4 sm:grid-cols-2 xl:grid-cols-4">
                @foreach ($reports as $type => $title)
                    <div class="rounded-lg border border-gray-200 bg-white p-5 shadow-sm">
                        <p class="text-xs font-bold uppercase text-gray-500">{{ strtoupper($type) }}</p>
                        <h3 class="mt-2 text-lg font-bold text-spms-ink">{{ $title }}</h3>
                        <p class="mt-2 text-sm leading-6 text-gray-600">Review commitments, targets, accomplishments, and computed evaluation summaries.</p>
                        <div class="mt-5 flex flex-wrap gap-2">
                            <a href="{{ route('reports.show', $type) }}" class="rounded-lg bg-spms-navy px-4 py-2 text-sm font-semibold text-white transition hover:bg-[#2b4276]">Generate</a>
                            <a href="{{ route('reports.pdf', $type) }}" class="rounded-lg border border-gray-300 px-4 py-2 text-sm font-semibold text-spms-ink transition hover:bg-gray-50">PDF</a>
                            <a href="{{ route('reports.excel', $type) }}" class="rounded-lg border border-gray-300 px-4 py-2 text-sm font-semibold text-spms-ink transition hover:bg-gray-50">Excel</a>
                        </div>
                    </div>
                @endforeach
            </div>
        </div>
    </div>
</x-app-layout>
