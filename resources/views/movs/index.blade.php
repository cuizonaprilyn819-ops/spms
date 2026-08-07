<x-app-layout>
    <x-slot name="header">
        <div class="flex flex-col gap-4 sm:flex-row sm:items-center sm:justify-between">
            <div>
                <h2 class="text-xl font-bold leading-tight text-spms-ink">{{ __('MOV Uploading') }}</h2>
                <p class="mt-1 text-sm text-gray-500">Upload and review means of verification files for performance accomplishments.</p>
            </div>
            <a href="{{ route('movs.create') }}" class="inline-flex items-center justify-center rounded-lg bg-spms-navy px-5 py-2.5 text-sm font-semibold text-white shadow-sm transition hover:bg-[#2b4276] focus:outline-none focus:ring-2 focus:ring-spms-sky focus:ring-offset-2">
                Upload MOV
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

            <div class="overflow-hidden rounded-lg border border-gray-200 bg-white shadow-sm">
                <div class="overflow-x-auto">
                    <table class="min-w-full divide-y divide-gray-200">
                        <thead class="bg-spms-surface">
                            <tr>
                                <th class="px-6 py-3 text-left text-xs font-bold uppercase text-gray-500">MOV</th>
                                <th class="px-6 py-3 text-left text-xs font-bold uppercase text-gray-500">File Type</th>
                                <th class="px-6 py-3 text-left text-xs font-bold uppercase text-gray-500">Size</th>
                                <th class="px-6 py-3 text-left text-xs font-bold uppercase text-gray-500">Uploaded By</th>
                                <th class="px-6 py-3 text-right text-xs font-bold uppercase text-gray-500">Actions</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-gray-200 bg-white">
                            @forelse ($movs as $mov)
                                <tr>
                                    <td class="px-6 py-4">
                                        <p class="font-semibold text-spms-ink">{{ $mov->title }}</p>
                                        <p class="mt-1 text-xs text-gray-500">{{ $mov->original_name }}</p>
                                    </td>
                                    <td class="px-6 py-4">
                                        <span class="inline-flex rounded-full bg-blue-50 px-3 py-1 text-xs font-bold uppercase text-spms-navy">{{ $mov->extension }}</span>
                                    </td>
                                    <td class="px-6 py-4 text-sm text-gray-600">{{ $mov->file_size_label }}</td>
                                    <td class="px-6 py-4 text-sm text-gray-600">{{ $mov->uploadedBy?->name }}</td>
                                    <td class="px-6 py-4">
                                        <div class="flex justify-end gap-2">
                                            <a href="{{ route('movs.show', $mov) }}" class="rounded-lg border border-gray-300 px-3 py-2 text-sm font-semibold text-spms-ink transition hover:bg-gray-50">View</a>
                                            <a href="{{ route('movs.download', $mov) }}" class="rounded-lg border border-gray-300 px-3 py-2 text-sm font-semibold text-spms-ink transition hover:bg-gray-50">Download</a>
                                        </div>
                                    </td>
                                </tr>
                            @empty
                                <tr>
                                    <td colspan="5" class="px-6 py-12 text-center text-sm text-gray-500">No MOV files uploaded yet.</td>
                                </tr>
                            @endforelse
                        </tbody>
                    </table>
                </div>
            </div>

            <div class="mt-5">
                {{ $movs->links() }}
            </div>
        </div>
    </div>
</x-app-layout>
