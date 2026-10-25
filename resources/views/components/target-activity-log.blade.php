@props(['activity'])
@if($activity !== null)
<section class="mt-5 overflow-hidden rounded-lg border border-gray-200 bg-white p-6 shadow-sm" aria-labelledby="target-activity-heading">
    <h2 id="target-activity-heading" class="text-lg font-bold">Target Activity Log</h2>
    <p class="mt-2 text-sm text-gray-500">Every MOV attachment appears here without waiting for the target to be marked complete. Marked completion is a separate activity. Updates every 15 seconds while the dashboard is visible.</p>
    <div class="mt-4 overflow-x-auto">
        <table class="min-w-full text-left text-sm">
            <thead><tr><th class="p-3">Date / Time</th><th class="p-3">Person</th><th class="p-3">Office</th><th class="p-3">Target</th><th class="p-3">Activity</th></tr></thead>
            <tbody>
                @forelse($activity as $event)
                <tr class="border-t border-gray-200">
                    <td class="whitespace-nowrap p-3">{{ $event->occurred_at }}</td>
                    <td class="p-3">{{ $event->actor }}</td>
                    <td class="p-3">{{ $event->office ?? 'Unassigned office' }}</td>
                    <td class="p-3"><a class="font-medium text-spms-navy hover:underline" href="{{ route('office-targets.show', $event->target_id) }}#target-movs">{{ $event->target_title }}</a></td>
                    <td class="p-3"><span class="font-semibold">{{ $event->action }}</span>@if($event->action === 'MOV attached')<span class="mt-1 block text-xs text-gray-500">{{ $event->detail }}</span>@endif</td>
                </tr>
                @empty
                <tr><td colspan="5" class="p-3 text-gray-500">No target activity in your scope yet.</td></tr>
                @endforelse
            </tbody>
        </table>
    </div>
    <div class="mt-4">{{ $activity->links() }}</div>
</section>
@endif
