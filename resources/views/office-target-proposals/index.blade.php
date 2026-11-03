<x-app-layout>
<x-slot name="header"><div class="flex flex-wrap items-center justify-between gap-3"><h2 class="text-xl font-bold text-spms-ink">Office target proposals</h2>@if(auth()->user()->canProposeOfficeTarget())<a href="{{ route('office-target-proposals.create') }}" class="rounded-lg bg-spms-navy px-4 py-2 text-sm font-semibold text-white">Add office target</a>@endif</div></x-slot>
<div class="mx-auto max-w-5xl space-y-5 px-4 py-8">
    @if(session('status'))<p role="status" class="rounded-lg bg-blue-50 p-4 text-sm">{{ session('status') }}</p>@endif
    @if($errors->any())<p role="alert" class="text-sm text-red-700">{{ $errors->first() }}</p>@endif
    @forelse($proposals as $proposal)
    <section class="space-y-3 rounded-xl border bg-white p-5">
        <div class="flex flex-wrap justify-between gap-2"><h3 class="font-bold">{{ $proposal->proposal['title'] }}</h3><span class="text-sm font-semibold capitalize">{{ $proposal->state === 'accepted' ? 'Validated' : $proposal->state }}</span></div>
        <p class="text-sm text-gray-500">{{ $proposal->office->name }}</p>
        <p class="text-sm"><strong>Strategic Program:</strong> {{ $proposal->proposal['program'] }}</p>
        <p class="text-sm"><strong>Program Outcome:</strong> {{ $proposal->proposal['program_outcome'] }}</p>
        <p class="whitespace-pre-line text-sm"><strong>Success Indicator:</strong> {{ $proposal->proposal['success_indicator'] }}</p>
        <p class="text-sm">Quantity: {{ $proposal->proposal['target_value'] }} {{ $proposal->proposal['unit_of_measure'] }} · Weight: {{ $proposal->proposal['weight'] }}%</p>
        <p class="whitespace-pre-line text-sm"><strong>Justification:</strong> {{ $proposal->proposal['justification'] }}</p>
        @if($proposal->review_notes)<p class="rounded-lg bg-gray-50 p-3 text-sm">Review notes: {{ $proposal->review_notes }}</p>@endif
        @if($proposal->proposal['approved_office_target_id'] ?? null)<a class="inline-block text-sm font-semibold underline" href="{{ route('office-targets.show', $proposal->proposal['approved_office_target_id']) }}">View validated office target</a>@endif
        @if($proposal->state === 'returned' && $proposal->submitted_by === auth()->id())<a class="inline-block text-sm font-semibold underline" href="{{ route('office-target-proposals.create', ['proposal_id' => $proposal->id]) }}">Revise and resubmit</a>@endif
        @if($proposal->state === 'pending' && $proposal->submitted_by !== auth()->id() && auth()->user()->hasAnyRole(['Planning Officer','Administrator']))
        <form method="POST" action="{{ route('office-target-proposals.review', $proposal) }}" class="space-y-3 border-t pt-4">@csrf
            <label class="block text-sm font-semibold">Validation decision<select name="decision" required class="mt-2 block w-full rounded-lg border-gray-300"><option value="approve">Validate and activate for this office</option><option value="return">Return for correction</option><option value="reject">Reject</option></select></label>
            <label class="block text-sm font-semibold">Review notes<textarea name="review_notes" required rows="3" maxlength="5000" class="mt-2 block w-full rounded-lg border-gray-300"></textarea></label>
            <button class="rounded-lg bg-spms-navy px-4 py-2 text-sm font-semibold text-white">Save decision</button>
        </form>
        @endif
    </section>
    @empty<p class="rounded-lg border bg-white p-5 text-sm text-gray-500">No office target proposals yet.</p>@endforelse
    {{ $proposals->links() }}
</div>
</x-app-layout>
