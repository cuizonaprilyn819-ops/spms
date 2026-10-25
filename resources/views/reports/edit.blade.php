<x-app-layout><x-slot name="header"><h2 class="text-xl font-bold text-spms-ink">Edit {{ strtoupper($type) }}</h2></x-slot>
<div class="py-8"><form method="POST" action="{{ route('report-workflow.update', [$type, $form]) }}" class="mx-auto max-w-3xl space-y-5 rounded-lg bg-white p-6 shadow-sm">
@csrf @method('PUT')
<div><x-input-label for="title" value="Title"/><x-text-input id="title" name="title" class="mt-1 block w-full" :value="old('title', $form->title)" required/><x-input-error :messages="$errors->get('title')" class="mt-2"/></div>
<div><x-input-label for="success_indicators" value="Success Indicators"/><textarea id="success_indicators" name="success_indicators" rows="6" class="mt-1 block w-full rounded-md border-gray-300">{{ old('success_indicators', $form->success_indicators) }}</textarea></div>
<div><x-input-label for="targets" value="Targets"/><textarea id="targets" name="targets" rows="6" class="mt-1 block w-full rounded-md border-gray-300">{{ old('targets', $form->targets) }}</textarea></div>
<div class="flex gap-3"><x-primary-button>Save changes</x-primary-button><a href="{{ route($type.'s.show', $form) }}" class="rounded-md border px-4 py-2 text-sm">Cancel</a></div>
</form></div></x-app-layout>
