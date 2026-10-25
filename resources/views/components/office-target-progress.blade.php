@props(['rows'])
<section class="mt-5 overflow-hidden rounded-lg border border-gray-200 bg-white p-6 shadow-sm">
    <h2 class="text-lg font-bold">Office Target Progress</h2>
    <p class="mt-2 text-sm text-gray-500">Reported quantities against your allocation. Employees mark completion with supporting MOV; the evaluator rates the work afterward.</p>
    <div class="mt-4 overflow-x-auto"><table class="min-w-full text-left text-sm">
        <thead><tr><th class="p-3">Target</th><th class="p-3">Completed / Allocated</th><th class="p-3">Progress</th><th class="p-3">Deadline</th><th class="p-3">Status</th></tr></thead>
        <tbody>@forelse($rows as $row)
            <tr class="border-t border-gray-200">
                <td class="p-3">{{ $row['title'] }}</td>
                <td class="p-3">{{ number_format($row['completed'], 2) }} / {{ $row['required'] === null ? 'Not set' : number_format($row['required'], 2) }} {{ $row['unit'] }}</td>
                <td class="p-3">{{ $row['percent'] === null ? 'Unavailable' : $row['percent'].'%' }}</td>
                <td class="p-3">{{ $row['deadline'] }}</td>
                <td class="p-3">{{ $row['status'] }} @if($row['overdue'])<strong class="text-red-700">· Overdue</strong>@endif</td>
            </tr>
        @empty<tr><td colspan="5" class="p-3 text-gray-500">No office targets in your scope.</td></tr>@endforelse</tbody>
    </table></div>
</section>
