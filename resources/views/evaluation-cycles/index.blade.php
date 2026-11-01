<x-app-layout>
    <x-slot name="header">
        <div class="flex flex-col gap-4 sm:flex-row sm:items-center sm:justify-between">
            <div>
                <h2 class="text-xl font-bold leading-tight text-spms-ink">{{ __('Evaluation Period Management') }}</h2>
                <p class="mt-1 text-sm text-gray-500">Create SPMS evaluation periods and control which quarter is open for evaluation.</p>
            </div>
            <a href="{{ route('evaluation-cycles.create') }}" class="inline-flex items-center justify-center rounded-lg bg-spms-navy px-5 py-2.5 text-sm font-semibold text-white shadow-sm transition hover:bg-spms-navy-hover focus:outline-none focus:ring-2 focus:ring-spms-sky focus:ring-offset-2">
                Create Period
            </a>
        </div>
    </x-slot>

    <div class="py-8">
        <div class="mx-auto max-w-7xl px-4 sm:px-6 lg:px-8">
            @if (session('status'))
                <div class="mb-5 rounded-lg border border-green-200 bg-green-50 px-4 py-3 text-sm font-medium text-green-700">
                    {{ session('status') }}
                </div>
            @endif
            @if($errors->any())<div role="alert" class="spms-alert mb-4 rounded-lg bg-red-50 p-4 text-sm text-red-700" data-tone="danger">@foreach($errors->all() as $error)<p>{{ $error }}</p>@endforeach</div>@endif
            @unless(app(\App\Services\PcrReminders::class)->emailAvailable())<p class="spms-alert mb-4 rounded-lg bg-amber-50 p-4 text-sm" data-tone="warning">Outgoing reminder email is not enabled. Contact the administrator to configure email delivery. In-app reminders are available when the scheduler runs.</p>@endunless

            <div class="overflow-hidden rounded-lg border border-gray-200 bg-white shadow-sm">
                <div class="overflow-x-auto">
                    <table data-workspace-table class="min-w-full divide-y divide-gray-200">
                        <thead class="bg-spms-surface">
                            <tr>
                                <th class="px-6 py-3 text-left text-xs font-bold uppercase text-gray-500">Period</th>
                                <th class="px-6 py-3 text-left text-xs font-bold uppercase text-gray-500">Year</th>
                                <th class="px-6 py-3 text-left text-xs font-bold uppercase text-gray-500">Quarter</th>
                                <th class="px-6 py-3 text-left text-xs font-bold uppercase text-gray-500">Status</th>
                                <th class="px-6 py-3 text-right text-xs font-bold uppercase text-gray-500">Actions</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-gray-200 bg-white">
                            @forelse ($cycles as $cycle)
                                <tr>
                                    <td class="px-6 py-4 font-semibold text-spms-ink">{{ $cycle->label }}</td>
                                    <td class="px-6 py-4 text-sm text-gray-600">{{ $cycle->year }}</td>
                                    <td class="px-6 py-4 text-sm text-gray-600">{{ App\Models\EvaluationCycle::semesterLabel($cycle->semester) }}</td>
                                    <td class="px-6 py-4">
                                        @if ($cycle->isOpen())
                                            <span class="spms-badge inline-flex rounded-full bg-green-50 px-3 py-1 text-xs font-semibold text-green-700" data-tone="success">Open</span>
                                        @else
                                            <span class="spms-badge inline-flex rounded-full bg-gray-100 px-3 py-1 text-xs font-semibold text-gray-600" data-tone="neutral">Closed</span>
                                        @endif
                                    </td>
                                    <td class="px-6 py-4">
                                        <p class="mb-2 text-sm">PCR creation: {{ $cycle->pcr_creation_closes_at ? ($cycle->pcr_creation_closes_at->isPast() ? 'Closed' : 'Closes').' '.$cycle->pcr_creation_closes_at->format('M j, Y') : 'Closing date not set' }}<br>Submission due: {{ $cycle->submissionDueAt()->format('M j, Y') }}</p>
                                        @hasanyrole('Administrator|Planning Officer')
                                        @if($cycle->isOpen())
                                        <details class="mb-3"><summary class="cursor-pointer text-sm font-semibold">PCR deadlines and reminders</summary>
                                            <form method="POST" action="{{ route('evaluation-cycles.deadlines', $cycle) }}" class="mt-3 space-y-2">@csrf @method('PATCH')
                                                <label class="block text-sm">PCR creation closes<input name="pcr_creation_closes_at" type="date" required value="{{ $cycle->pcr_creation_closes_at?->format('Y-m-d') }}" class="ml-2 rounded-lg border-gray-300"></label>
                                                <label class="block text-sm">Submission deadline<input name="pcr_submission_due_at" type="date" required value="{{ $cycle->pcr_submission_due_at?->format('Y-m-d') }}" class="ml-2 rounded-lg border-gray-300"></label>
                                                <label class="block text-sm">Reminder window (days)<input name="pcr_reminder_days" type="number" min="1" max="60" required value="{{ $cycle->pcr_reminder_days ?? 7 }}" class="ml-2 w-20 rounded-lg border-gray-300"></label>
                                                <p class="text-xs text-gray-500">Daily reminders until completion. Dates include the end of the selected day ({{ config('app.timezone') }}). Existing submission deadline snapshots are preserved.</p>
                                                <button class="rounded-lg border px-3 py-2 text-sm font-semibold">Save PCR deadlines</button>
                                            </form>
                                        </details>
                                        @endif
                                        @endhasanyrole
                                        <p class="mb-2 text-sm">PMT Chairperson: {{ $cycle->pmtChairperson?->name ?? 'Unassigned' }}</p>
                                        @hasanyrole('Administrator|Planning Officer')
                                        @if($cycle->isOpen())
                                        <form method="POST" action="{{ route('evaluation-cycles.chairperson', $cycle) }}" class="mb-3 flex gap-2">@csrf @method('PATCH')
                                            <select name="pmt_chairperson_id" aria-label="PMT chairperson for {{ $cycle->label }}" required class="rounded-lg border-gray-300"><option value="">Select chairperson</option>@foreach($people as $person)<option value="{{ $person->id }}" @selected($cycle->pmt_chairperson_id === $person->id)>{{ $person->name }}</option>@endforeach</select>
                                            <button class="rounded-lg border px-3 py-2">Assign chairperson</button>
                                        </form>
                                        @endif
                                        @endhasanyrole
                                        <div class="flex justify-end gap-2">
                                            @if ($cycle->isOpen())
                                                <form method="POST" action="{{ route('evaluation-cycles.close', $cycle) }}">
                                                    @csrf
                                                    @method('PATCH')
                                                    <button type="submit" class="rounded-lg border border-spms-red px-3 py-2 text-sm font-semibold text-spms-red transition hover:bg-spms-red hover:text-white">Close Period</button>
                                                </form>
                                            @else
                                                <form method="POST" action="{{ route('evaluation-cycles.open', $cycle) }}">
                                                    @csrf
                                                    @method('PATCH')
                                                    <button type="submit" class="rounded-lg border border-spms-green px-3 py-2 text-sm font-semibold text-spms-green transition hover:bg-spms-green hover:text-white">Open Period</button>
                                                </form>
                                            @endif
                                        </div>
                                    </td>
                                </tr>
                            @empty
                                <tr>
                                    <td colspan="5" class="px-6 py-12 text-center text-sm text-gray-500">No evaluation periods yet.</td>
                                </tr>
                            @endforelse
                        </tbody>
                    </table>
                </div>
            </div>

            <div class="mt-5">
                {{ $cycles->links() }}
            </div>
        </div>
    </div>
</x-app-layout>
