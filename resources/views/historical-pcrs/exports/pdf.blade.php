<!doctype html>
<html lang="en"><head><meta charset="utf-8"><style>
@page { margin: 24pt; } body { font-family: DejaVu Sans, sans-serif; font-size: 8pt; color:#152536; }
h1 { font-size:12pt; text-align:center; margin:0 0 10pt; } p { margin:3pt 0; }
table { border-collapse:collapse; width:100%; table-layout:fixed; margin-top:10pt; } th,td { border:0.5pt solid #a9b3bf; padding:5pt; vertical-align:top; overflow-wrap:break-word; }
th,.section { background:#e8edf2; font-weight:bold; } thead { display:table-header-group; } tr { page-break-inside:avoid; }
.evidence { page-break-before:always; text-align:center; } .evidence img { max-width:880pt; max-height:515pt; } .closing { margin-top:12pt; } .note { color:#6b4a13; }
</style></head><body>
<h1>{{ strtoupper($title) }}</h1>
@foreach(app(\App\Services\HistoricalPcrExport::class)->intro(get_defined_vars()) as $line)<p>{{ $line }}</p>@endforeach
<table><thead><tr>@foreach($columns as $column)<th style="width:{{ $column['width'] }}%">{{ $column['label'] }}</th>@endforeach</tr></thead><tbody>
@php($last = null)
@foreach($rows as $row)
@if($row['section'] !== $last)<tr><td class="section" colspan="{{ count($columns) }}">{{ $row['section'] }}</td></tr>@php($last = $row['section'])@endif
<tr>@foreach($columns as $key => $column)<td>{!! nl2br(e($row[$key] ?? '')) !!}</td>@endforeach</tr>
@endforeach
</tbody></table>
<div class="closing">@foreach(app(\App\Services\HistoricalPcrExport::class)->closing(get_defined_vars()) as $line)<p>{{ $line }}</p>@endforeach</div>
@foreach($evidence as $item)<div class="evidence"><p>Evidence - original source page {{ $item['page'] }}</p><img src="data:image/jpeg;base64,{{ base64_encode(file_get_contents($item['path'])) }}" alt="Original evidence page {{ $item['page'] }}"></div>@endforeach
</body></html>
