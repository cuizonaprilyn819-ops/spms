@props(['mov'])
            @if(in_array(strtolower(pathinfo($mov->path, PATHINFO_EXTENSION)), ['pdf', 'jpg', 'jpeg', 'png']))
                <div x-data="{ previewOpen: false }">
                    <button type="button" class="mt-2 text-sm text-spms-navy underline" @click="$refs.previewDialog.showModal(); previewOpen = true">View MOV: {{ $mov->original_name }}</button>
                    <dialog x-ref="previewDialog" @close="previewOpen = false" class="w-full max-w-5xl rounded-lg bg-white p-4 shadow-lg" style="height: 85vh" aria-label="MOV preview: {{ $mov->original_name }}">
                        <div class="flex items-center justify-between gap-4 pb-3">
                            <p class="truncate text-sm font-semibold">{{ $mov->original_name }}</p>
                            <button type="button" autofocus @click="$refs.previewDialog.close()" class="rounded-lg border border-gray-300 px-4 py-2 text-sm">Close ×</button>
                        </div>
                        <template x-if="previewOpen">
                            @if(strtolower(pathinfo($mov->path, PATHINFO_EXTENSION)) === 'pdf')
                                <iframe src="{{ route('target-movs.preview', $mov) }}" title="PDF preview" class="w-full border-0" style="height: calc(100% - 60px)"></iframe>
                            @else
                                <img src="{{ route('target-movs.preview', $mov) }}" alt="{{ $mov->original_name }}" class="w-full object-contain" style="height: calc(100% - 60px)">
                            @endif
                        </template>
                    </dialog>
                </div>
            @else
                <a class="mt-2 block text-sm text-spms-navy underline" href="{{ route('target-movs.download', $mov) }}">Download existing document: {{ $mov->original_name }}</a>
            @endif