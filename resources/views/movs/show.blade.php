<x-app-layout>
    <x-slot name="header">
        <div class="flex flex-col gap-4 sm:flex-row sm:items-center sm:justify-between">
            <div>
                <h2 class="text-xl font-bold leading-tight text-spms-ink">{{ $mov->title }}</h2>
                <p class="mt-1 text-sm text-gray-500">Uploaded {{ $mov->created_at->format('F d, Y') }} by {{ $mov->uploadedBy?->name }}.</p>
            </div>
            <a href="{{ route('movs.download', $mov) }}" class="inline-flex items-center justify-center rounded-lg bg-spms-navy px-5 py-2.5 text-sm font-semibold text-white shadow-sm transition hover:bg-spms-navy-hover focus:outline-none focus:ring-2 focus:ring-spms-sky focus:ring-offset-2">
                Download File
            </a>
        </div>
    </x-slot>

    <div class="py-8">
        <div class="mx-auto max-w-4xl px-4 sm:px-6 lg:px-8">
            @if (session('status'))
                <div class="mb-5 rounded-lg border border-green-200 bg-green-50 px-4 py-3 text-sm font-medium text-green-700">
                    {{ session('status') }}
                </div>
            @endif

            <div class="rounded-lg border border-gray-200 bg-white p-6 shadow-sm">
                <dl class="grid gap-6 sm:grid-cols-2">
                    <div>
                        <dt class="text-xs font-bold uppercase text-gray-500">Original Filename</dt>
                        <dd class="mt-1 text-sm font-semibold text-spms-ink">{{ $mov->original_name }}</dd>
                    </div>
                    <div>
                        <dt class="text-xs font-bold uppercase text-gray-500">File Type</dt>
                        <dd class="mt-1 text-sm font-semibold uppercase text-spms-ink">{{ $mov->extension }}</dd>
                    </div>
                    <div>
                        <dt class="text-xs font-bold uppercase text-gray-500">File Size</dt>
                        <dd class="mt-1 text-sm font-semibold text-spms-ink">{{ $mov->file_size_label }}</dd>
                    </div>
                    <div>
                        <dt class="text-xs font-bold uppercase text-gray-500">MIME Type</dt>
                        <dd class="mt-1 text-sm font-semibold text-spms-ink">{{ $mov->mime_type }}</dd>
                    </div>
                    <div class="sm:col-span-2">
                        <dt class="text-xs font-bold uppercase text-gray-500">Description</dt>
                        <dd class="mt-1 whitespace-pre-line text-sm leading-6 text-gray-600">{{ $mov->description ?: 'No description provided.' }}</dd>
                    </div>
                </dl>

                @if (in_array($mov->extension, ['jpg', 'jpeg'], true))
                    <div class="mt-6 overflow-hidden rounded-lg border border-gray-200 bg-spms-surface">
                        <img src="{{ $mov->file_url }}" alt="{{ $mov->title }}" class="max-h-[32rem] w-full object-contain">
                    </div>
                @endif

                <div class="mt-6">
                    <a href="{{ route('movs.index') }}" class="rounded-lg border border-gray-300 px-5 py-3 text-sm font-semibold text-spms-ink transition hover:bg-gray-50">Back to MOV</a>
                </div>
            </div>
        </div>
    </div>
</x-app-layout>
