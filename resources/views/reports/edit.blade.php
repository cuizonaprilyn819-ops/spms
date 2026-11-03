<x-app-layout><x-slot name="header"><h2 class="text-xl font-bold text-spms-ink">Edit {{ strtoupper($type) }}</h2></x-slot>
<div class="py-8">
<div class="mx-auto mb-5 max-w-3xl space-y-3">
@if(session('status'))<p role="status" class="rounded-lg bg-blue-50 p-3 text-sm">{{ session('status') }}</p>@endif
@if($errors->any())<p role="alert" class="rounded-lg bg-red-50 p-3 text-sm text-red-700">{{ $errors->first() }}</p>@endif
@if(!$form->commitmentPlan)
<form method="POST" action="{{ route('report-workflow.paraphrase', [$type, $form]) }}" x-data="{busy:false}" @submit="busy=true">@csrf<button :disabled="busy" class="rounded-lg border px-4 py-2 text-sm font-semibold" x-text="busy ? 'Preparing report wording…' : 'AI paraphrase program, outcome and indicator'">AI paraphrase program, outcome and indicator</button><p class="mt-2 text-xs text-gray-600">Uses saved source fields. Save manual source additions first. Replaces draft wording; review before submission.</p></form>
@endif
@if($targetContext['source_excerpt'] ?? null)<details class="text-sm"><summary>Original source for comparison</summary><p class="mt-2 whitespace-pre-line">{{ $targetContext['source_excerpt'] }}</p></details>@endif
</div>
<form method="POST" action="{{ route('report-workflow.update', [$type, $form]) }}" class="mx-auto max-w-3xl space-y-5 rounded-lg bg-white p-6 shadow-sm">
@csrf @method('PUT')
<div><x-input-label for="title" value="Title"/><x-text-input id="title" name="title" class="mt-1 block w-full" :value="old('title', $form->title)" required/><x-input-error :messages="$errors->get('title')" class="mt-2"/></div>
<div><x-input-label for="strategic_program" value="Strategic Program"/><textarea id="strategic_program" name="strategic_program" rows="3" class="mt-1 block w-full rounded-md border-gray-300">{{ old('strategic_program', $targetContext['strategic_program'] ?? '') }}</textarea></div>
<div><x-input-label for="program_outcome" value="Program Outcome"/><textarea id="program_outcome" name="program_outcome" rows="3" class="mt-1 block w-full rounded-md border-gray-300">{{ old('program_outcome', $targetContext['program_outcome'] ?? '') }}</textarea></div>
<div><x-input-label for="success_indicators" value="Success Indicators"/><textarea id="success_indicators" name="success_indicators" rows="6" class="mt-1 block w-full rounded-md border-gray-300">{{ old('success_indicators', $form->success_indicators) }}</textarea></div>
<div><x-input-label for="targets" value="Targets"/><textarea id="targets" name="targets" rows="6" class="mt-1 block w-full rounded-md border-gray-300">{{ old('targets', $form->targets) }}</textarea></div>
<div class="flex gap-3"><x-primary-button>Save changes</x-primary-button><a href="{{ route($type.'s.show', $form) }}" class="rounded-md border px-4 py-2 text-sm">Cancel</a></div>
</form></div></x-app-layout>
