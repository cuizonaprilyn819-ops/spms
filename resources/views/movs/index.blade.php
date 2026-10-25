<x-app-layout>
    <x-slot name="header">
        <h2 class="text-xl font-bold text-spms-ink">Means of Verification (MOV)</h2>
        <p class="mt-1 text-sm text-gray-500">Files submitted for assigned targets. Open a target to submit additional evidence.</p>
    </x-slot>
    <div class="py-8">
        <div class="mx-auto max-w-7xl px-4 sm:px-6 lg:px-8">
            <div class="overflow-x-auto rounded-lg border border-gray-200 bg-white shadow-sm">
                <table class="min-w-full divide-y divide-gray-200">
                    <thead class="bg-gray-50"><tr>
                        <th class="px-6 py-3 text-left text-xs font-bold text-gray-500">TARGET</th>
                        <th class="px-6 py-3 text-left text-xs font-bold text-gray-500">UPLOADED BY</th>
                        <th class="px-6 py-3 text-left text-xs font-bold text-gray-500">DATE</th>
                        <th class="px-6 py-3 text-left text-xs font-bold text-gray-500">FILE / PREVIEW</th>
                    </tr></thead>
                    <tbody class="divide-y divide-gray-200">
                        @forelse($movs as $mov)
                            <tr>
                                <td class="px-6 py-4 text-sm"><a class="font-semibold text-spms-navy underline" href="{{ route('office-targets.show', $mov->office_target_id) }}">{{ $mov->target->title }}</a></td>
                                <td class="px-6 py-4 text-sm">{{ $mov->employee->name }}</td>
                                <td class="px-6 py-4 text-sm">{{ $mov->created_at->format('M d, Y h:i A') }}</td>
                                <td class="px-6 py-4"><x-mov-preview :mov="$mov" /></td>
                            </tr>
                        @empty
                            <tr><td colspan="4" class="px-6 py-12 text-center text-sm text-gray-500">No target MOV submissions available yet.</td></tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
            <div class="mt-5">{{ $movs->links() }}</div>
        </div>
    </div>
</x-app-layout>