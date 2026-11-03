@props(['document'])
@php
    $drafts = $document->drafts;
    $officeWeights = App\Models\OfficeTarget::whereIn('id', $drafts->pluck('office_target_id')->filter())->pluck('weight', 'id');
    $planWeights = App\Models\PerformanceTarget::whereIn('id', $drafts->pluck('performance_target_id')->filter())->pluck('weight', 'id');
    $known = $drafts->filter(fn ($d) => is_numeric($d->proposal['weight'] ?? null));
    $total = $drafts->sum(fn ($d) => (float) ($officeWeights[$d->office_target_id] ?? $planWeights[$d->performance_target_id] ?? $d->proposal['weight'] ?? 0));
    $assigned = $officeWeights->sum() + $planWeights->sum();
@endphp
<section class="rounded-xl border bg-white p-6">
    <h2 class="font-bold">Target weights and measures</h2>
    <p class="mt-2 text-sm">Total weight: <strong>{{ number_format($total, 2) }}%</strong> / 100% · Assigned/imported: {{ number_format($assigned, 2) }}% · Unallocated budget: {{ number_format(max(0, 100 - $total), 2) }}%</p>
    @if($known->count() !== $drafts->count())<p class="mt-2 text-sm text-amber-800">{{ $drafts->count() - $known->count() }} target(s) still need a weight.</p>@endif
    @if(abs($total - 100) > 0.005)<p class="mt-2 text-sm text-amber-800">Weights are not balanced to 100%. Review the complete target list and section budgets.</p>@endif
    <p class="mt-2 text-xs text-gray-600">Draft suggestions are included in the total. Each target counts once even when assigned to multiple offices. Quantities with different units are shown separately.</p>
    @foreach($drafts->filter(fn ($d) => isset($d->proposal['weight_budget']))->groupBy(fn ($d) => $d->proposal['weight_budget']['id']) as $group)
        @php($budget = $group->first()->proposal['weight_budget'])
        <div class="mt-4 border-t pt-3 text-sm">
            <p class="font-semibold">{{ $budget['label'] }}: {{ number_format($group->sum(fn ($d) => (float) ($officeWeights[$d->office_target_id] ?? $planWeights[$d->performance_target_id] ?? $d->proposal['weight'] ?? 0)), 2) }}% / {{ number_format($budget['budget'], 2) }}%</p>
            <p class="mt-1 whitespace-pre-line text-xs text-gray-600">Source/budget basis: {{ $budget['source_basis'] }}</p>
        </div>
    @endforeach
    @foreach($drafts->pluck('proposal')->pluck('weight_warnings')->filter()->unique() as $warning)
        <p role="alert" class="mt-3 whitespace-pre-line rounded-lg bg-amber-50 p-3 text-sm text-amber-900">{{ $warning }}</p>
    @endforeach
</section>
