<section class="mt-6 overflow-hidden rounded-xl border border-gray-200 bg-white shadow-sm" aria-labelledby="objectives-heading">
    <div class="flex flex-wrap items-center justify-between gap-3 border-b border-gray-200 p-6">
        <div>
            <h3 id="objectives-heading" class="font-bold text-spms-ink">Objectives under this goal</h3>
            <p class="mt-1 text-sm text-gray-500">Each objective can have multiple targets.</p>
        </div>
        <a href="{{ route('strategic-objectives.create', $goal) }}#objective-form" class="rounded-lg border border-spms-navy px-4 py-2 text-sm font-semibold text-spms-navy">Add Objective</a>
    </div>
    <div class="space-y-4 p-6">
        @forelse($goal->objectives as $listedObjective)
            <div class="rounded-lg border border-gray-200 p-4">
                <div class="flex flex-col gap-3 sm:flex-row sm:items-start sm:justify-between">
                    <h4 class="min-w-0 break-words font-semibold text-spms-ink">{{ $listedObjective->title }}</h4>
                    <a href="{{ route('office-targets.create', ['strategic_objective_id' => $listedObjective->id]) }}" class="shrink-0 self-start rounded-lg bg-spms-navy px-4 py-2 text-sm font-semibold text-white">Add Target</a>
                </div>
                <ul class="mt-3 space-y-2 text-sm">
                    @forelse($listedObjective->officeTargets as $target)
                        <li><a href="{{ route('office-targets.show', $target) }}" class="font-medium text-spms-navy hover:underline">{{ $target->title }}</a></li>
                    @empty
                        <li class="text-gray-500">No targets yet. Add the first target for this objective.</li>
                    @endforelse
                </ul>
            </div>
        @empty
            <p class="text-sm text-gray-500">Add your first objective using the form above.</p>
        @endforelse
    </div>
</section>
