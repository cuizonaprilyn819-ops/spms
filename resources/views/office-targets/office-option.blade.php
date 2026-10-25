<span class="block font-semibold">{{ $office->name }}</span>
<span class="mt-1 block text-xs text-gray-600">
    {{ $office->campus !== null ? 'Campus '.$office->campus : 'Campus unspecified' }}
    · {{ $office->code ?: 'No office code' }} · Office #{{ $office->id }}
    @if(! $office->is_active) · Inactive @endif
</span>
@foreach($office->targetHeads as $head)
    <span class="mt-1 block text-xs text-gray-600">{{ $head->name }} — {{ $head->roles->pluck('name')->join(', ') }}</span>
@endforeach
@php($recipients = $office->targetHeads->filter(fn ($head) => $head->hasAnyRole(['Supervisor', 'HR Head'])))
@if($recipients->isNotEmpty())
    <span class="mt-1 block text-xs font-medium text-green-700">Will notify: {{ $recipients->pluck('name')->join(', ') }}</span>
@else
    <span class="mt-1 block text-xs font-medium text-amber-700">No notification recipient configured. Ask an administrator to assign the correct office supervisor.</span>
@endif
