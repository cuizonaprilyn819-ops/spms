<div class="space-y-6">
    @forelse ($forms as $form)
        <section class="overflow-hidden rounded-lg border border-gray-200 bg-white p-5 shadow-sm">
            <div class="text-center">
                <h3 class="text-base font-bold uppercase text-spms-ink">{{ $form['heading'] }}</h3>
                <p class="mx-auto mt-3 max-w-5xl text-sm leading-6 text-gray-700">{{ $form['commitment'] }}</p>
            </div>

            <div class="mt-5 grid gap-4 md:grid-cols-2">
                <div class="rounded-lg border border-gray-200 p-4">
                    <p class="text-xs font-bold uppercase text-gray-500">Prepared By</p>
                    <p class="mt-4 font-bold text-spms-ink">{{ $form['prepared_name'] }}</p>
                    <p class="text-sm text-gray-600">{{ $form['prepared_position'] }}</p>
                </div>

                <div class="rounded-lg border border-gray-200 p-4">
                    <p class="text-xs font-bold uppercase text-gray-500">{{ $form['reviewed_by'] ? 'Reviewed / Approved By' : 'Approved By' }}</p>
                    @if ($form['reviewed_by'])
                        <p class="mt-4 font-bold text-spms-ink">{{ $form['reviewed_by'] }}</p>
                        <p class="text-sm text-gray-600">Reviewer</p>
                    @endif
                    <p class="{{ $form['reviewed_by'] ? 'mt-3' : 'mt-4' }} font-bold text-spms-ink">{{ $form['approved_by'] }}</p>
                    <p class="text-sm text-gray-600">{{ $form['approved_position'] }}</p>
                </div>
            </div>

            <div class="mt-5 rounded-lg border border-gray-200 p-4">
                <p class="text-xs font-bold uppercase text-gray-500">Rating Scale</p>
                <div class="mt-2 flex flex-wrap gap-x-5 gap-y-1 text-sm text-gray-700">
                    @foreach ($form['rating_scale'] as $scale)
                        <span>{{ $scale }}</span>
                    @endforeach
                </div>
            </div>

            <div class="mt-5 overflow-x-auto">
                <table class="min-w-full border-collapse text-sm">
                    <thead>
                        <tr class="bg-spms-surface">
                            @foreach ($formHeaders as $header)
                                <th class="border border-gray-300 px-3 py-2 text-left text-xs font-bold uppercase text-gray-600">{{ $header }}</th>
                            @endforeach
                        </tr>
                    </thead>
                    <tbody>
                        @forelse ($form['rows'] as $row)
                            <tr>
                                @foreach ($row as $cell)
                                    <td class="border border-gray-300 px-3 py-3 align-top text-gray-700">{{ $cell }}</td>
                                @endforeach
                            </tr>
                        @empty
                            <tr>
                                <td colspan="{{ count($formHeaders) }}" class="border border-gray-300 px-3 py-8 text-center text-gray-500">No performance targets encoded yet.</td>
                            </tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
        </section>
    @empty
        <div class="rounded-lg border border-gray-200 bg-white px-6 py-12 text-center text-sm text-gray-500 shadow-sm">
            No form records available.
        </div>
    @endforelse
</div>
