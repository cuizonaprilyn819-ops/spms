<div class="spms-period-reference" aria-label="Current evaluation period">
    <span>Current period</span>
    <strong>{{ $currentPeriod ? $currentPeriod->periodStart()->format('F').' – '.$currentPeriod->periodEnd()->format('F Y') : 'No open period' }}</strong>
    <small>{{ $currentPeriod ? \App\Models\EvaluationCycle::semesterLabel($currentPeriod->semester) : 'Ask an administrator to configure a period.' }}</small>
</div>
