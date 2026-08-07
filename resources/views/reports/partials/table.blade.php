<table class="min-w-full divide-y divide-gray-200">
    <thead class="bg-spms-surface">
        <tr>
            @foreach ($headers as $header)
                <th class="px-5 py-3 text-left text-xs font-bold uppercase text-gray-500">{{ $header }}</th>
            @endforeach
        </tr>
    </thead>
    <tbody class="divide-y divide-gray-200 bg-white">
        @forelse ($rows as $row)
            <tr>
                @foreach ($row as $cell)
                    <td class="px-5 py-4 text-sm text-gray-700">{{ $cell }}</td>
                @endforeach
            </tr>
        @empty
            <tr>
                <td colspan="{{ count($headers) }}" class="px-5 py-12 text-center text-sm text-gray-500">No records available for this report.</td>
            </tr>
        @endforelse
    </tbody>
</table>
