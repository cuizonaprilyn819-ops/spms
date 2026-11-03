<x-app-layout>
    <x-slot name="header">
        <h2 class="text-xl font-bold leading-tight text-spms-ink">{{ __('Upload MOV') }}</h2>
    </x-slot>

    <div class="py-8">
        <div class="mx-auto max-w-4xl px-4 sm:px-6 lg:px-8">
            <div class="rounded-lg border border-gray-200 bg-white p-6 shadow-sm">
                <p class="mb-4 text-sm font-semibold text-spms-navy">{{ $questionnaire ? 'Step 2: Answer the questions and attach your MOV files' : 'Step 1: Choose your target' }}</p>
                @if($errors->any())<p role="alert" class="mb-4 rounded-lg bg-red-50 p-3 text-sm text-red-700">{{ $errors->first() }}</p>@endif
                <form method="POST" action="{{ $questionnaire ? route('movs.store') : route('movs.questions') }}" enctype="multipart/form-data" class="space-y-6">
                    @csrf
                    @if($questionnaire)
                        <input type="hidden" name="questionnaire_token" value="{{ $questionnaireToken }}">
                        <input type="hidden" name="target_ref" value="{{ $questionnaire['target_ref'] }}">
                    @endif

                    <div>
                        <x-input-label for="target_ref" :value="__('Select target for this MOV')" />
                        <select @disabled($questionnaire !== null) id="target_ref" name="target_ref" required class="mt-1 block w-full rounded-lg border-gray-300">
                            <option value="">Select your target…</option>
                            <optgroup label="My accepted assigned targets">
                            @foreach($officeTargets as $target)
                                <option value="office:{{ $target->id }}" @selected(old('target_ref', $questionnaire['target_ref'] ?? (request('office_target_id') ? 'office:'.request('office_target_id') : '')) === 'office:'.$target->id)>{{ $target->title }} — {{ $target->evaluationCycle?->label }}</option>
                            @endforeach
                            </optgroup>
                            <optgroup label="Targets in my reports">
                            @foreach($targets as $target)
                                <option value="report:{{ $target->id }}" @selected(old('target_ref', $questionnaire['target_ref'] ?? (old('performance_target_id', request('performance_target_id')) ? 'report:'.old('performance_target_id', request('performance_target_id')) : '')) === 'report:'.$target->id)>{{ $target->target }} — {{ $target->targetable?->title }}</option>
                            @endforeach
                            </optgroup>
                        </select>
                        <p class="mt-2 text-xs text-gray-500">Select the target the file proves. Assigned targets appear after you accept the assignment. AI checks the file against the selected target.</p>
                        @if($officeTargets->isEmpty() && $targets->isEmpty())<p class="mt-2 text-sm text-amber-800">No uploadable targets yet. Accept your assigned target or add a target to your report first.</p>@endif
                        <x-input-error :messages="$errors->get('target_ref')" class="mt-2" />
                        <x-input-error :messages="$errors->get('performance_target_id')" class="mt-2" />
                    </div>

                    @if($questionnaire)
                    <section class="space-y-4 rounded-lg border border-blue-200 bg-blue-50 p-4">
                        <h3 class="font-bold">AI questions for this target</h3>
                        <p class="text-sm">{{ $questionnaire['target_snapshot']['success_indicator'] }}</p>
                        <p class="text-sm">Confirmed allocation: {{ $questionnaire['target_snapshot']['quantity'] ?? 'See target indicator' }} {{ $questionnaire['target_snapshot']['unit'] }} · Due: {{ $questionnaire['target_snapshot']['deadline'] ?? 'See evaluation period' }}</p>
                        @foreach($questionnaire['questions'] as $question)
                        <div><label for="answer-{{ $question['id'] }}" class="block text-sm font-semibold">{{ $question['label'] }} {{ $question['required'] ? '*' : '(optional)' }}</label>
                            @if($question['type'] === 'text')<textarea id="answer-{{ $question['id'] }}" name="answers[{{ $question['id'] }}]" rows="3" maxlength="2000" @required($question['required']) class="mt-2 block w-full rounded-lg border-gray-300">{{ old('answers.'.$question['id']) }}</textarea>
                            @else<input id="answer-{{ $question['id'] }}" name="answers[{{ $question['id'] }}]" type="{{ $question['type'] }}" @if($question['type'] === 'number') min="0" max="9999999999.99" step="0.01" @endif @required($question['required']) value="{{ old('answers.'.$question['id']) }}" class="mt-2 block w-full rounded-lg border-gray-300">@endif
                            <x-input-error :messages="$errors->get('answers.'.$question['id'])" class="mt-1" />
                        </div>
                        @endforeach
                        <p class="text-sm"><strong>Evidence to attach:</strong> {{ $questionnaire['guidance'] }}</p>
                        <p class="text-xs text-gray-600">Your answers are reported claims. AI will check them against the actual files and identify missing or contradictory evidence.</p>
                    </section>
                    @if($progress)
                    <label class="block text-sm font-semibold">Total completed so far ({{ $questionnaire['target_snapshot']['unit'] }})<input name="completed_value" type="number" min="0" max="9999999999.99" step="0.01" required value="{{ old('completed_value', $progress->completed_value) }}" class="mt-2 block w-full rounded-lg border-gray-300"></label>
                    <p class="text-xs text-gray-600">Enter the cumulative completed quantity, not an additional amount. Your answers and MOV files should support this figure.</p>
                    @endif
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
                        <x-input-label for="files" :value="__('MOV Files')" />
                        <input id="files" name="files[]" type="file" multiple onchange="const files = Array.from(this.files); this.setCustomValidity(files.length > 20 ? 'Select at most 20 files.' : files.some(file => file.size > 10 * 1024 * 1024) ? 'Each file must be 10 MB or smaller.' : files.reduce((sum, file) => sum + file.size, 0) > 35 * 1024 * 1024 ? 'Select up to 35 MB per batch.' : ''); this.reportValidity();" accept=".pdf,.docx,.xlsx,.jpg,.jpeg,.png" class="mt-1 block w-full rounded-lg border border-gray-300 bg-white px-3 py-2 text-sm text-gray-700 shadow-sm file:mr-4 file:rounded-lg file:border-0 file:bg-spms-navy file:px-4 file:py-2 file:text-sm file:font-semibold file:text-white hover:file:bg-[#2b4276] focus:border-spms-sky focus:outline-none focus:ring-spms-sky" required>
                        <p class="mt-2 text-xs text-gray-500">Select up to 20 files at once. Maximum 10 MB per file and 35 MB per batch. Hold Ctrl or Shift to select multiple files. Assigned targets accept PDF, JPG or PNG; report targets also accept DOCX and XLSX.</p>
                        <x-input-error :messages="$errors->get('files')" class="mt-2" />
                        @foreach($errors->get('files.*') as $messages)<x-input-error :messages="$messages" class="mt-2" />@endforeach
                        <x-input-error :messages="$errors->get('file')" class="mt-2" />
                    </div>

                    @endif
                    <div class="flex items-center justify-end gap-3">
                        <a href="{{ route('movs.index') }}" class="rounded-lg border border-gray-300 px-5 py-3 text-sm font-semibold text-spms-ink transition hover:bg-gray-50">Cancel</a>
                        <x-primary-button>{{ $questionnaire ? 'Upload files and analyze answers' : 'Prepare AI questions' }}</x-primary-button>
                    </div>
                </form>
            </div>
        </div>
    </div>
</x-app-layout>
