<!DOCTYPE html>
<html>
<head>
    <meta charset="utf-8">
    <title>Individual Performance Commitment and Review</title>
    <style>
        @page { margin: 32pt 40pt; }
        body { font-family: DejaVu Sans, sans-serif; font-size: 8pt; color: #000; }
        h1 { font-size: 10pt; text-align: center; margin: 0 0 18pt; }
        p { margin: 5pt 0; }
        table { width: 100%; border-collapse: collapse; table-layout: fixed; }
        th, td { border: .6pt solid #000; padding: 3pt 4pt; vertical-align: top; overflow-wrap: break-word; }
        th { font-weight: normal; }
        thead { display: table-header-group; }
        tr { page-break-inside: avoid; }
        .center { text-align: center; }
        .signature { margin: 22pt 0 14pt 74%; line-height: 1.5; }
        .signatures td { height: 38pt; vertical-align: bottom; }
        .scale { margin-bottom: 18pt; }
        .scale td { height: auto; vertical-align: top; }
        .section { font-weight: bold; }
        .target td { min-height: 28pt; white-space: pre-line; }
        .comments { height: 35pt; }
        .closing { margin-top: 12pt; page-break-inside: avoid; }
        .closing-signatures td { height: 48pt; }
        .legend { font-size: 7pt; font-style: italic; }
        .new-page { page-break-before: always; }
        .context { font-size: 7pt; margin: 5pt 0; }
    </style>
</head>
<body>
@forelse ($forms as $form)
    @php($school = $form['school_format'])
    <div @class(['new-page' => !$loop->first])>
        <h1>INDIVIDUAL PERFORMANCE COMMITMENT AND REVIEW (IPCR)</h1>
        <p>{{ $form['commitment'] }}</p>
        <div class="signature"><strong>{{ $school['employee'] }}</strong><br>{{ $school['position'] }}<br>Date: ______________</div>
        <table class="signatures">
            <tr><th style="width:31%">Reviewed by:</th><th style="width:39%">Approved by:</th><th>Date:</th></tr>
            <tr><td><strong>{{ $school['reviewer'] }}</strong><br>{{ $school['reviewer_position'] }}</td><td><strong>{{ $school['approver'] }}</strong><br>{{ $school['approver_position'] }}<br>{{ $school['approver_office'] }}</td><td>{{ $school['approval_date'] }}</td></tr>
        </table>
        <table class="scale"><tr><td style="width:31%"><strong>RATING SCALE</strong></td><td style="width:39%"></td><td>@foreach ($form['rating_scale'] as $scale){{ $scale }}@if(!$loop->last)<br>@endif @endforeach</td></tr></table>
        @if($form['strategic_program'] || $form['program_outcome'])
            <div class="context">
                @if($form['strategic_program'])<strong>Strategic Program:</strong> {{ $form['strategic_program'] }}<br>@endif
                @if($form['program_outcome'])<strong>Program Outcome:</strong> {{ $form['program_outcome'] }}@endif
            </div>
        @endif
        <table>
            <colgroup><col style="width:36.5%"><col style="width:9.5%"><col style="width:20%"><col style="width:4.3%"><col style="width:4.3%"><col style="width:4.3%"><col style="width:4.3%"><col style="width:16.8%"></colgroup>
            <thead><tr><th style="width:36.5%" class="center" rowspan="2">Success Indicator<br>(Target + Measure)</th><th style="width:9.5%" rowspan="2" class="center">Allotted Budget</th><th style="width:20%" rowspan="2" class="center">Actual Accomplishments</th><th style="width:17.2%" colspan="4" class="center">Rating</th><th style="width:16.8%" rowspan="2" class="center">Remarks</th></tr><tr><th>Q<sup>1</sup></th><th>E<sup>2</sup></th><th>T<sup>3</sup></th><th>A<sup>4</sup></th></tr></thead>
            <tbody>
            @foreach ($school['sections'] as $label => $sectionRows)
                @continue(empty($sectionRows) && !in_array($label, ['Strategic Objectives', 'Core Functions']))
                <tr><td colspan="8" class="section">{{ strtoupper($label) }}@if(isset($school['weights'][$label])) ({{ number_format($school['weights'][$label], 2) }}%)@endif</td></tr>
                @forelse($sectionRows as $row)
                    <tr class="target">@foreach($row as $cell)<td>{{ $cell }}</td>@endforeach</tr>
                @empty
                    <tr><td colspan="8">&nbsp;</td></tr>
                @endforelse
            @endforeach
                <tr><td><strong>Total Overall Rating</strong></td><td colspan="7"></td></tr>
                <tr><td>Final Average Rating</td><td colspan="5"></td><td>{{ $school['average'] }}</td><td></td></tr>
                <tr><td>Adjectival Rating</td><td colspan="7"></td></tr>

            </tbody>
        </table>
        <div class="closing">
        <table><tr><td><strong>Comments and Recommendation for Development Purposes</strong></td></tr><tr><td class="comments">{{ $school['comments'] }}</td></tr></table>
        <table class="closing-signatures" style="margin-top:12pt">
            <tr><th style="width:36%">Discussed with:</th><th style="width:9%">Date</th><th style="width:22%">Assessed by:</th><th style="width:9%">Date</th><th style="width:16%">Approved by:</th><th>Date</th></tr>
            <tr><td class="center"><br><br><strong>{{ $school['employee'] }}</strong><br>{{ $school['position'] }}</td><td></td><td>I hereby certify that I discussed my assessment of the performance with the employee.<br><br><strong>{{ $school['approver'] }}</strong><br>{{ $school['approver_position'] }}</td><td></td><td></td><td></td></tr>
        </table>
        <p class="legend">Legend &nbsp; 1 - Quality &nbsp;&nbsp; 2 - Efficiency &nbsp;&nbsp; 3 - Timeliness &nbsp;&nbsp; 4 - Average</p>
        </div>
    </div>
    <div class="new-page">
        <h2 style="font-size:10pt">MEANS OF VERIFICATION (MOV)</h2>
        <p>{{ $school['employee'] }}@if($school['office']) - {{ $school['office'] }}@endif</p>
        @forelse($school['evidence'] as $evidence)
            <p><strong>{{ $loop->iteration }}. {{ $evidence['name'] }}</strong><br>{{ $evidence['description'] }}</p>
        @empty
            <p>No MOV files recorded for this report.</p>
        @endforelse
    </div>
@empty
    <h1>INDIVIDUAL PERFORMANCE COMMITMENT AND REVIEW (IPCR)</h1>
    <p>No records available for this report.</p>
@endforelse
</body>
</html>
