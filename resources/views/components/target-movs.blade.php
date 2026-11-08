@props(['target'])
@php
    $user = Auth::user();
    $canSubmit = $target->assignedEmployees()->whereKey($user->id)->exists() && $target->offices()->whereKey($user->office_id)->exists();
    $managed = app(\App\Services\TargetHierarchy::class)->managedOfficeIds($user);
    $canReview = $target->offices()->whereIn('offices.id', $managed)->exists();
    $isDirector = $user->hasAnyRole(['Campus Director', 'Planning Officer']) && (int) $target->created_by === (int) $user->id;
@endphp
@if($canSubmit || $canReview || $isDirector)
<section id="target-movs" class="mt-6 rounded-lg border border-gray-200 bg-white p-6 shadow-sm" data-action-plain>
    <h2 class="text-lg font-bold text-spms-ink">Means of Verification (MOV)</h2>
    @if(session('mov_status'))<p role="status" class="mt-3 text-sm text-green-700">{{ session('mov_status') }}</p>@endif
    @if($errors->any())<div role="alert" class="mt-4 rounded-lg border border-red-200 bg-red-50 p-3 text-sm text-red-700">@foreach($errors->all() as $error)<p>{{ $error }}</p>@endforeach</div>@endif
    @if($canSubmit)
    @php
        $assignment = $target->assignedEmployees()->whereKey($user->id)->first();
    @endphp
    @unless($assignment->pivot->accepted_at)<form method="POST" action="{{ route('office-targets.accept', $target) }}" class="mt-3">@csrf @method('PATCH')<button class="rounded-lg border px-4 py-2">Accept assignment</button></form>@endunless
    @if($assignment->pivot->completed_at)
        <p class="mt-3 text-sm text-green-700">You marked this target complete. The evaluator will rate your work afterward.</p>
    @elseif(\App\Models\TargetMov::where('office_target_id', $target->id)->where('user_id', $user->id)->exists())
        <form method="POST" action="{{ route('target-movs.complete', $target) }}" class="mt-4">
            @csrf @method('PATCH')
            <x-primary-button>Mark My Target Complete</x-primary-button>
            <p class="mt-2 text-xs text-gray-500">Reports your completion to your supervisor and the assigning Campus Director.</p>
        </form>
    @endif
    @if($assignment->pivot->accepted_at)
    @if(config('spms.ai.enabled'))
    <a href="{{ route('movs.create', ['office_target_id' => $target->id]) }}" class="mt-4 inline-flex min-h-11 items-center rounded-lg bg-spms-navy px-4 py-2 text-sm font-semibold text-white">Upload MOV files</a>
    <p class="mt-2 text-xs text-gray-600">Attach files first. AI analyzes the evidence, then asks only for missing details before preparing your report summary.</p>
    @else
    <form method="POST" action="{{ route('target-movs.store', $target) }}" enctype="multipart/form-data" class="mt-4 space-y-3">
        @csrf
        @if($assignment->pivot->allocated_target_value !== null && !$assignment->pivot->completed_at)
        <label for="completed-value" class="block text-sm font-semibold">Total completed so far ({{ $target->unit_of_measure }})</label>
        <input id="completed-value" name="completed_value" type="number" min="0" step="0.01" max="9999999999.99" value="{{ old('completed_value', $assignment->pivot->completed_value ?? 0) }}" required class="block w-full rounded-lg border-gray-300">
        <p class="text-xs text-gray-500">Your allocation: {{ $assignment->pivot->allocated_target_value }}. Enter the cumulative total, not an additional amount. After submitting your MOV, mark the target complete when your work is finished. Evaluation takes place afterward.</p>
        <x-input-error :messages="$errors->get('completed_value')" />
        @endif
        <x-input-label for="accomplishment" value="Accomplishment Details" />
        <textarea id="accomplishment" name="accomplishment" rows="3" required maxlength="5000" class="block w-full rounded-lg border-gray-300" placeholder="Describe the work completed for this target.">{{ old('accomplishment') }}</textarea>
        <x-input-error :messages="$errors->get('accomplishment')" />
        <x-input-label for="mov-file" value="Supporting File" />
        <input id="mov-file" type="file" name="files[]" multiple accept=".pdf,.jpg,.jpeg,.png,.webp" required class="block w-full text-sm" onchange="this.setCustomValidity(this.files.length > 20 ? 'Select a maximum of 20 files or images.' : Array.from(this.files).reduce((sum, file) => sum + file.size, 0) > 35 * 1024 * 1024 ? 'Select up to 35 MB per batch.' : ''); this.reportValidity();">
        <p class="text-xs text-gray-500">Up to 20 PDF, JPG, JPEG, PNG, or WebP files per submission. Maximum 10 MB per file and 35 MB per batch. Save Word or Excel documents as PDF before uploading.</p>
        <x-input-error :messages="$errors->get('files')" />
        @foreach ($errors->get('files.*') as $fileErrors)
            <x-input-error :messages="$fileErrors" />
        @endforeach
        <x-primary-button>Submit MOV</x-primary-button>
    </form>
    @endif
    @endif
    @endif
    @php
        $movs = \App\Models\TargetMov::with(['employee:id,name', 'analysis'])->where('office_target_id', $target->id)
            ->when(! $isDirector, fn ($q) => $canReview ? $q->whereIn('office_id', $managed) : $q->where('user_id', $user->id))
            ->latest()->orderByDesc('id')->paginate(10, ['*'], 'target_mov_page')->withQueryString()->fragment('target-movs');
    @endphp
    <div class="mt-5 space-y-4">
        @forelse($movs as $mov)
        <article class="rounded-lg border border-gray-200 p-4">
            <p class="text-sm font-semibold">{{ $mov->employee->name }} <span class="text-gray-500">— {{ $mov->created_at->format('M d, Y h:i A') }}</span></p>
            <p class="mt-2 whitespace-pre-line text-sm">{{ $mov->accomplishment }}</p>
            <x-mov-preview :mov="$mov" />
            <x-evidence-analysis :evidence="$mov" />
        </article>
        @empty<p class="text-sm text-gray-500">No MOV submitted yet.</p>@endforelse
    </div>
    <div class="mt-4">{{ $movs->links() }}</div>
</section>
@endif
