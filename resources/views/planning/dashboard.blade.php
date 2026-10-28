
@php
    $planningService=app(\App\Services\CommitmentPlanning::class);
    $visiblePlans=\App\Models\CommitmentPlan::with(['office','employee'])->where('state','approved')->get()->filter(fn($p)=>$planningService->organization->canView(auth()->user(),$p));
@endphp
@if($visiblePlans->isNotEmpty())<section class="mx-6 my-5 rounded-lg border border-gray-200 bg-white p-5"><div class="flex justify-between gap-4"><h2 class="text-lg font-semibold">Commitment progress</h2><a class="text-blue-700 underline" href="{{ route('planning.index') }}">Open planning</a></div><p class="my-2 text-sm text-gray-600">Each row is a separate level. Reported work and validated work are shown separately; do not add these rows together.</p><div class="overflow-x-auto"><table class="planning-table"><thead><tr><th>Level / responsibility</th><th>Target</th><th>Committed</th><th>Reported</th><th>Validated</th><th>Unallocated</th></tr></thead><tbody>
@foreach($visiblePlans as $p)
@foreach($p->targets as $target)
@php($progress=$planningService->summary($target))<tr><td><a class="text-blue-700 underline" href="{{ route('planning.show',$p) }}">{{ strtoupper($p->level) }} · {{ $p->employee?->name ?? $p->office->name }}</a></td><td>{{ $target->target }}
@if($target->needs_reconciliation)<br>Reconciliation required
@endif</td><td>{{ $target->quantity }} {{ $target->unit }}</td><td>{{ $progress['reported'] ?? 'Unavailable' }}</td><td>{{ $progress['validated'] ?? 'Unavailable' }}</td><td>{{ $progress['unallocated'] ?? 'Not applicable' }}</td></tr>
@endforeach
@endforeach</tbody></table></div></section>
@endif
