
@php($line = $line ?? null)
<label>Target title<input name="target" required maxlength="255" value="{{ old('target', $line?->target ?? ($prefill['title'] ?? '')) }}"></label>
<label>Success indicator<textarea name="success_indicator" required>{{ old('success_indicator', $line?->success_indicator ?? ($prefill['success_indicator'] ?? '')) }}</textarea></label>
<div class="grid gap-3 sm:grid-cols-3">
    <label>Measurement<select name="measurement">
@foreach(['count'=>'Divisible count','rate'=>'Percentage / rate','shared'=>'Shared output','manual'=>'Manual review'] as $v=>$label)<option value="{{ $v }}"
@selected(old('measurement',$line?->measurement)===$v)>{{ $label }}</option>
@endforeach</select></label>
    <label>Committed quantity / percentage<input type="number" name="quantity" step="0.01" min="0.01" required value="{{ old('quantity',$line?->quantity ?? ($prefill['target_value'] ?? '')) }}"></label>
    <label>Unit of measure<input name="unit" required maxlength="100" value="{{ old('unit',$line?->unit ?? ($prefill['unit_of_measure'] ?? '')) }}" placeholder="documents, sessions, %"></label>
</div>
<div class="grid gap-3 sm:grid-cols-3">
    <label>Count precision<select name="whole_numbers"><option value="1"
@selected($line?->whole_numbers !== false)>Whole outputs</option><option value="0"
@selected($line?->whole_numbers === false)>Two decimal places</option></select></label>
    <label>Deadline<input type="date" name="deadline" required value="{{ old('deadline',$line?->deadline?->format('Y-m-d') ?? ($prefill['deadline'] ?? '')) }}"></label>
    <label>Rating weight (%)<input name="weight" type="number" min="0" max="100" step="0.01" required value="{{ old('weight',$line?->weight ?? ($prefill['weight'] ?? 0)) }}"></label>
</div>
<label>Quality and timeliness standards<textarea name="standards" required>{{ old('standards',$line?->standards) }}</textarea></label>
<label>Source and supporting passage<textarea name="source_evidence" required>{{ old('source_evidence',$line?->source_evidence ?? ($prefill['source_excerpt'] ?? '')) }}</textarea></label>
