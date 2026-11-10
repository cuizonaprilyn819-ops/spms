@php($scope = $scope ?? [])
<fieldset class="spms-scope-fields">
    <legend>Who is responsible for this target?</legend>
    <label>Coverage<select name="coverage" required>
        @foreach(['departments'=>'Selected departments / offices','campus'=>'One campus','university'=>'University-wide'] as $value=>$label)
        @continue($value==='university' && $plan->office->organization_scope==='campus')
        <option value="{{ $value }}" @selected(old('coverage',$scope['coverage'] ?? '')===$value)>{{ $label }}</option>
        @endforeach
    </select></label>
    @if($plan->cycle->multi_campus_enabled)
    <fieldset><legend>Participating campuses</legend>
        @foreach($campuses as $campus)<label class="spms-choice"><input type="checkbox" name="campus_ids[]" value="{{ $campus->id }}" @checked(in_array($campus->id,old('campus_ids',$scope['campus_ids'] ?? [])))> {{ $campus->name }}</label>@endforeach
    </fieldset>
    @endif
    <fieldset><legend>Responsible departments</legend><p class="text-sm text-slate-600">Choose only departments whose recorded duties support the target. Campus or university coverage does not assign every department.</p>
        @forelse($recipientOffices ?? $offices as $office)
        <label class="spms-choice"><input type="checkbox" name="recipient_ids[]" value="{{ $office->id }}" @checked(in_array($office->id,old('recipient_ids',$scope['recipient_ids'] ?? [])))><span>{{ $office->name }} @if($office->campusRecord) · {{ $office->campusRecord->name }} @endif<small class="block text-slate-600">{{ $office->mandate ?: 'Mandate missing — verification required' }}</small></span></label>
        @empty<p class="spms-alert" data-tone="warning">No department branches are configured. Verify their reporting relationships and mandates before confirming a target.</p>@endforelse
    </fieldset>
    <label>What does the entered quantity mean?<select name="quantity_basis" required>
        <option value="total" @selected(old('quantity_basis',$scope['quantity_basis'] ?? 'total')==='total')>Combined total across selected departments</option>
        <option value="per_department" @selected(old('quantity_basis',$scope['quantity_basis'] ?? '')==='per_department')>This quantity for each selected department (counts only)</option>
    </select></label>
    <p class="text-sm text-slate-600">For two departments: 2 combined means 2 altogether; 2 each means a total commitment of 4. Combined totals need confirmed office shares; no equal split is assumed.</p>
    <label>Why are these departments responsible?<textarea name="matching_reason" required maxlength="5000">{{ old('matching_reason',$scope['matching_reason'] ?? '') }}</textarea></label>
</fieldset>
