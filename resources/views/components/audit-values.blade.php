@props(['values'])
@if(is_array($values))
<dl class="space-y-2 border-l border-slate-200 pl-3">
@foreach($values as $key => $value)
<div><dt class="text-xs font-semibold text-gray-500">{{ is_numeric($key) ? 'Entry '.($key + 1) : ucfirst(str_replace('_', ' ', $key)) }}</dt><dd class="mt-1 whitespace-pre-line break-words text-sm">@if(is_array($value))<x-audit-values :values="$value" />@else{{ is_bool($value) ? ($value ? 'Yes' : 'No') : ($value ?? 'Not recorded') }}@endif</dd></div>
@endforeach
</dl>
@else<p class="text-sm text-gray-500">Not recorded</p>@endif
