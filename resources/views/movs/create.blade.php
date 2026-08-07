<x-app-layout>
    <x-slot name="header">
        <h2 class="text-xl font-bold leading-tight text-spms-ink">{{ __('Upload MOV') }}</h2>
    </x-slot>

    <div class="py-8">
        <div class="mx-auto max-w-4xl px-4 sm:px-6 lg:px-8">
            <div class="rounded-lg border border-gray-200 bg-white p-6 shadow-sm">
                <form method="POST" action="{{ route('movs.store') }}" enctype="multipart/form-data" class="space-y-6">
                    @csrf

                    <div>
                        <x-input-label for="title" :value="__('MOV Title')" />
                        <x-text-input id="title" name="title" type="text" class="mt-1 block w-full" :value="old('title')" placeholder="Example: Signed accomplishment report" required />
                        <x-input-error :messages="$errors->get('title')" class="mt-2" />
                    </div>

                    <div>
                        <x-input-label for="description" :value="__('Description')" />
                        <textarea id="description" name="description" rows="4" class="mt-1 block w-full rounded-lg border-gray-300 shadow-sm focus:border-spms-sky focus:ring-spms-sky" placeholder="Optional notes about this MOV.">{{ old('description') }}</textarea>
                        <x-input-error :messages="$errors->get('description')" class="mt-2" />
                    </div>

                    <div>
                        <x-input-label for="file" :value="__('MOV File')" />
                        <input id="file" name="file" type="file" accept=".pdf,.docx,.xlsx,.jpg,.jpeg" class="mt-1 block w-full rounded-lg border border-gray-300 bg-white px-3 py-2 text-sm text-gray-700 shadow-sm file:mr-4 file:rounded-lg file:border-0 file:bg-spms-navy file:px-4 file:py-2 file:text-sm file:font-semibold file:text-white hover:file:bg-[#2b4276] focus:border-spms-sky focus:outline-none focus:ring-spms-sky" required>
                        <p class="mt-2 text-xs text-gray-500">Accepted formats: PDF, DOCX, XLSX, JPG. Maximum file size: 10 MB.</p>
                        <x-input-error :messages="$errors->get('file')" class="mt-2" />
                    </div>

                    <div class="flex items-center justify-end gap-3">
                        <a href="{{ route('movs.index') }}" class="rounded-lg border border-gray-300 px-5 py-3 text-sm font-semibold text-spms-ink transition hover:bg-gray-50">Cancel</a>
                        <x-primary-button>Upload MOV</x-primary-button>
                    </div>
                </form>
            </div>
        </div>
    </div>
</x-app-layout>
