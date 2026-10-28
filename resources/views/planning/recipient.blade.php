<label>Office<select name="office_id" required><option value="{{ $plan->office_id }}">{{ $plan->office->name }} (personnel below)</option>
@foreach($offices as $o)<option value="{{ $o->id }}">{{ $o->name }}</option>
@endforeach</select></label>
<label>Person (leave blank for an office allocation)<select name="employee_id"><option value="">Office allocation</option>
@foreach($people as $person)<option value="{{ $person->id }}">{{ $person->name }}</option>
@endforeach</select></label>
