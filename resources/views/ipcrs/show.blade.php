<x-app-layout>
    <x-slot name="header">
        <div class="flex flex-col gap-4 sm:flex-row sm:items-center sm:justify-between">
            <div>
                <h2 class="text-xl font-bold leading-tight text-spms-ink">{{ __('View IPCR') }}</h2>
                <p class="mt-1 text-sm text-gray-500">Assigned to {{ $ipcr->employee->name }}</p>
            </div>
            <a href="{{ route('ipcrs.index') }}" class="rounded-lg border border-gray-300 px-5 py-2.5 text-sm font-semibold text-spms-ink transition hover:bg-gray-50">Back</a>
        </div>
    </x-slot>

    <div class="py-8">
        <div class="mx-auto max-w-5xl px-4 sm:px-6 lg:px-8">
            @if (session('status'))
                <div class="mb-5 rounded-lg border border-green-200 bg-green-50 px-4 py-3 text-sm font-medium text-green-700">
                    {{ session('status') }}
                </div>
            @endif

            <div class="rounded-lg border border-gray-200 bg-white p-6 shadow-sm">
                <div class="flex flex-col gap-4 border-b border-gray-200 pb-6 sm:flex-row sm:items-start sm:justify-between">
                    <div>
                        <p class="text-sm font-semibold uppercase text-spms-sky">Individual Performance Commitment and Review</p>
                        <h1 class="mt-2 text-2xl font-bold text-spms-ink">{{ $ipcr->title }}</h1>
                        <p class="mt-2 text-sm text-gray-500">Prepared by {{ $ipcr->preparedBy?->name }}</p>
                    </div>
                    <div class="flex items-center gap-3">
                        @if ($ipcr->isApproved())
                            <span class="rounded-full bg-green-50 px-3 py-1 text-xs font-semibold text-green-700">Approved</span>
                        @else
                            <span class="rounded-full bg-amber-50 px-3 py-1 text-xs font-semibold text-amber-700">Draft</span>
                            @hasanyrole('Administrator|Campus Director|Office Head|Supervisor')
                                <form method="POST" action="{{ route('ipcrs.approve', $ipcr) }}">
                                    @csrf
                                    @method('PATCH')
                                    <button type="submit" class="rounded-lg bg-spms-green px-5 py-2.5 text-sm font-semibold text-white transition hover:bg-green-600">Approve IPCR</button>
                                </form>
                            @endhasanyrole
                        @endif
                    </div>
                </div>

                <div class="mt-6 grid gap-4 sm:grid-cols-3">
                    <div class="rounded-lg border border-gray-200 p-4">
                        <p class="text-xs font-bold uppercase text-gray-500">Linked UPCR</p>
                        <p class="mt-2 text-sm font-semibold text-spms-ink">{{ $ipcr->upcr->title }}</p>
                    </div>
                    <div class="rounded-lg border border-gray-200 p-4">
                        <p class="text-xs font-bold uppercase text-gray-500">Assigned Employee</p>
                        <p class="mt-2 text-sm font-semibold text-spms-ink">{{ $ipcr->employee->name }}</p>
                    </div>
                    <div class="rounded-lg border border-gray-200 p-4">
                        <p class="text-xs font-bold uppercase text-gray-500">Office / Cycle</p>
                        <p class="mt-2 text-sm font-semibold text-spms-ink">{{ $ipcr->upcr->dpcr->opcr->office->name }}</p>
                        <p class="mt-1 text-xs text-gray-500">{{ $ipcr->upcr->dpcr->opcr->evaluationCycle->label }}</p>
                    </div>
                </div>

                <div class="mt-8 space-y-6">
                    <section>
                        <h3 class="text-lg font-bold text-spms-ink">Success Indicators</h3>
                        <div class="mt-3 whitespace-pre-line rounded-lg border border-gray-200 bg-spms-surface p-4 text-sm leading-6 text-gray-700">{{ $ipcr->success_indicators ?: 'No success indicators encoded yet.' }}</div>
                    </section>

                    <section>
                        <h3 class="text-lg font-bold text-spms-ink">Targets</h3>
                        <div class="mt-3 whitespace-pre-line rounded-lg border border-gray-200 bg-spms-surface p-4 text-sm leading-6 text-gray-700">{{ $ipcr->targets ?: 'No targets encoded yet.' }}</div>
                    </section>
                </div>
            </div>
        </div>
    </div>
</x-app-layout>
