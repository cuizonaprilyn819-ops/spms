@props(['subject'])
@if(Auth::user()->hasRole('Administrator') || (int) $subject->created_by === (int) Auth::id())
<section class="mt-6 rounded-lg border border-gray-200 bg-white p-6">
    <h3 class="text-lg font-bold text-spms-ink">Target change audit log</h3>
    <p class="mt-1 text-sm text-gray-500">Original AI suggestions and confirmed changes are retained with the actor and time.</p>
    @foreach(\App\Models\WorkflowAudit::where('subject_type', $subject->getMorphClass())->where('subject_id', $subject->id)->with('actor')->latest('id')->get() as $event)
        <details class="mt-4 border-t border-gray-200 pt-3">
            <summary class="cursor-pointer text-sm font-semibold">{{ $event->action }} - {{ $event->actor?->name ?? 'Deleted user' }} - {{ $event->created_at->format('M d, Y h:i:s A') }}</summary>
            <div class="mt-3 grid gap-3 sm:grid-cols-2">
                <div><h4 class="text-xs font-bold uppercase">Before</h4><div class="mt-2 max-h-80 overflow-auto"><x-audit-values :values="$event->before" /></div></div>
                <div><h4 class="text-xs font-bold uppercase">After</h4><div class="mt-2 max-h-80 overflow-auto"><x-audit-values :values="$event->after" /></div></div>
            </div>
        </details>
    @endforeach
</section>
@endif
